<?php

namespace Tests\Feature;

use App\Mail\VoucherNotificationMail;
use App\Models\EmailLog;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use App\Services\VoucherAcknowledgementPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PublicVoucherAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.notifications.admin_email', null);
    }

    public function test_guest_without_account_can_open_public_token_and_email_uses_public_base_url(): void
    {
        Mail::fake();
        [$voucher, $acknowledgement, $token] = $this->pendingAcknowledgement();
        config()->set('services.vouchers.public_url', 'https://dana.wargacakna.test');

        $this->assertDatabaseMissing('users', ['email' => $voucher->recipient_email]);
        $this->get(route('vouchers.acknowledgement.show', $token))
            ->assertOk()
            ->assertSeeText($voucher->voucher_no)
            ->assertSeeText('Tiada akaun atau pendaftaran diperlukan', false);
        $this->assertGuest();

        $mail = new VoucherNotificationMail($voucher, 'payment_confirmed', $token);
        $html = $mail->render();
        $expectedUrl = 'https://dana.wargacakna.test/baucar/akuan/'.$token;

        $this->assertStringContainsString($expectedUrl, $html);
        $this->assertSame(hash('sha256', $token), $acknowledgement->token_hash);
        $this->assertNotSame($token, $acknowledgement->token_hash);
    }

    public function test_invalid_expired_and_used_tokens_have_controlled_responses_without_record_disclosure(): void
    {
        [$voucher] = $this->pendingAcknowledgement();
        $invalid = str_repeat('z', 64);

        $this->get(route('vouchers.acknowledgement.show', $invalid))
            ->assertNotFound()
            ->assertSeeText('Pautan ini tidak sah atau telah digantikan.')
            ->assertDontSeeText($voucher->voucher_no);

        [, , $expiredToken] = $this->pendingAcknowledgement(
            acknowledgementOverrides: ['expires_at' => now()->subMinute()],
        );
        $this->get(route('vouchers.acknowledgement.show', $expiredToken))
            ->assertStatus(410)
            ->assertSeeText('Pautan ini telah tamat tempoh.');

        [, $usedAcknowledgement, $usedToken] = $this->pendingAcknowledgement(
            voucherOverrides: ['status' => PaymentVoucher::COMPLETED, 'completed_at' => now()],
            acknowledgementOverrides: ['used_at' => now(), 'recipient_name' => 'Penerima Selesai'],
        );
        $usedAt = $usedAcknowledgement->used_at;
        $this->get(route('vouchers.acknowledgement.show', $usedToken))
            ->assertStatus(410)
            ->assertSeeText('Pautan ini telah digunakan');
        $this->post(route('vouchers.acknowledgement.store', $usedToken), [
            'recipient_name' => 'Cubaan Kedua',
            'accepted' => '1',
        ])->assertStatus(410);
        $this->assertTrue($usedAt->equalTo($usedAcknowledgement->fresh()->used_at));
    }

    public function test_guest_completion_creates_completed_pdf_emails_and_email_logs_for_all_parties(): void
    {
        Mail::fake();
        [$voucher, $acknowledgement, $token, $applicant, $admin] = $this->pendingAcknowledgement();

        $this->post(route('vouchers.acknowledgement.store', $token), [
            'recipient_name' => 'Penerima Disahkan',
            'accepted' => '1',
            'notes' => 'Bayaran telah diterima.',
        ])->assertOk()
            ->assertSeeText('SURAT AKUAN PENERIMAAN BAYARAN')
            ->assertSeeText($acknowledgement->acknowledgement_no)
            ->assertSeeText($voucher->voucher_no);
        $this->assertGuest();

        $voucher->refresh();
        $acknowledgement->refresh();
        $this->assertSame(PaymentVoucher::COMPLETED, $voucher->status);
        $this->assertNotNull($voucher->completed_at);
        $this->assertNotNull($acknowledgement->used_at);

        $expected = [
            'recipient' => $voucher->recipient_email,
            'applicant' => $applicant->email,
            'admin' => $admin->email,
        ];
        foreach ($expected as $audience => $email) {
            Mail::assertSent(VoucherNotificationMail::class, function (VoucherNotificationMail $mail) use ($audience, $email, $acknowledgement) {
                $attachments = $mail->attachments();

                return $mail->event === 'acknowledgement_completed'
                    && $mail->audience === $audience
                    && $mail->acknowledgement?->is($acknowledgement)
                    && $mail->hasTo($email)
                    && count($attachments) === 1
                    && $attachments[0]->as === "SURAT-AKUAN-PENERIMAAN-BAYARAN-{$acknowledgement->acknowledgement_no}.pdf"
                    && $attachments[0]->mime === 'application/pdf';
            });
        }

        $this->assertDatabaseHas('email_logs', [
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT,
            'related_id' => $acknowledgement->id,
            'status' => EmailLog::SENT,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_APPLICANT,
            'related_id' => $acknowledgement->id,
            'status' => EmailLog::SENT,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_ADMIN,
            'related_id' => $acknowledgement->id,
            'status' => EmailLog::SENT,
        ]);
        $this->assertSame(3, EmailLog::query()->count());
        foreach (EmailLog::all() as $log) {
            $this->assertStringNotContainsString($token, json_encode($log->getAttributes(), JSON_THROW_ON_ERROR));
        }

        $pdfHtml = view('pdf.voucher-acknowledgement', [
            'voucher' => $voucher->load('applicant', 'approver'),
            'acknowledgement' => $acknowledgement,
            'images' => [],
            'branding' => [],
        ])->render();
        $this->assertStringContainsString($acknowledgement->acknowledgement_no, $pdfHtml);
        $this->assertStringContainsString($voucher->voucher_no, $pdfHtml);
        $this->assertStringContainsString('SURAT AKUAN PENERIMAAN BAYARAN', $pdfHtml);
        $this->assertStringStartsWith('%PDF', app(VoucherAcknowledgementPdfService::class)->render($acknowledgement));
    }

    public function test_pdf_generation_failure_does_not_rollback_completion_and_is_logged_for_resend(): void
    {
        [$voucher, $acknowledgement, $token] = $this->pendingAcknowledgement();
        $documents = Mockery::mock(VoucherAcknowledgementPdfService::class);
        $documents->shouldReceive('filename')->times(3)->andReturn('SURAT-AKUAN-PENERIMAAN-BAYARAN.pdf');
        $documents->shouldReceive('render')->times(3)->andThrow(new RuntimeException('PDF renderer unavailable'));
        $this->app->instance(VoucherAcknowledgementPdfService::class, $documents);

        $this->post(route('vouchers.acknowledgement.store', $token), [
            'recipient_name' => 'Penerima Disahkan',
            'accepted' => '1',
        ])->assertOk()->assertSeeText('SURAT AKUAN PENERIMAAN BAYARAN');

        $this->assertSame(PaymentVoucher::COMPLETED, $voucher->fresh()->status);
        $this->assertNotNull($acknowledgement->fresh()->used_at);
        $this->assertSame(3, EmailLog::query()->where('status', EmailLog::FAILED)->count());
        $this->assertSame(3, EmailLog::query()->where('related_id', $acknowledgement->id)->count());
    }

    public function test_completed_email_resend_keeps_used_token_and_does_not_reopen_acknowledgement(): void
    {
        Mail::fake();
        [$voucher, $acknowledgement, , , $admin] = $this->pendingAcknowledgement(
            voucherOverrides: ['status' => PaymentVoucher::COMPLETED, 'completed_at' => now()],
            acknowledgementOverrides: ['used_at' => now(), 'recipient_name' => 'Penerima Selesai'],
        );
        $tokenHash = $acknowledgement->token_hash;
        $usedAt = $acknowledgement->used_at;
        $original = EmailLog::query()->create([
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT,
            'recipient_name' => $voucher->recipient_name,
            'recipient_email' => $voucher->recipient_email,
            'subject' => 'Akuan penerimaan selesai',
            'related_type' => EmailLog::RELATED_ACKNOWLEDGEMENT,
            'related_id' => $acknowledgement->id,
            'related_reference' => $acknowledgement->acknowledgement_no,
            'status' => EmailLog::FAILED,
            'attempted_at' => now(),
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('success');

        $acknowledgement->refresh();
        $this->assertSame($tokenHash, $acknowledgement->token_hash);
        $this->assertTrue($usedAt->equalTo($acknowledgement->used_at));
        $this->assertSame(1, $voucher->acknowledgements()->count());
        $this->assertSame(EmailLog::SENT, $original->resends()->sole()->status);
        Mail::assertSent(VoucherNotificationMail::class, fn (VoucherNotificationMail $mail) => $mail->event === 'acknowledgement_completed'
            && $mail->acknowledgement?->is($acknowledgement)
            && count($mail->attachments()) === 1);
    }

    /** @return array{PaymentVoucher, VoucherAcknowledgement, string, User, User} */
    private function pendingAcknowledgement(array $voucherOverrides = [], array $acknowledgementOverrides = []): array
    {
        $sequence = PaymentVoucher::query()->count() + 1;
        $applicant = User::factory()->create([
            'role' => 'ajk',
            'name' => "Pemohon Baucar {$sequence}",
            'email' => "applicant{$sequence}@example.test",
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => "Admin Baucar {$sequence}",
            'email' => "admin{$sequence}@example.test",
        ]);
        $voucher = PaymentVoucher::query()->create(array_merge([
            'voucher_no' => 'B-2026-1101-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'voucher_date' => '2026-11-01',
            'status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
            'applicant_id' => $applicant->id,
            'approver_id' => $admin->id,
            'recipient_name' => "Penerima Tanpa Akaun {$sequence}",
            'recipient_phone' => '0191234567',
            'recipient_email' => "recipient{$sequence}@example.test",
            'purpose' => 'Bantuan komuniti',
            'amount' => '125.50',
            'paid_amount' => '125.50',
            'transaction_date' => '2026-11-01',
            'transaction_reference' => "BANK-TXN-{$sequence}",
            'payment_confirmed_by' => $admin->id,
            'payment_confirmed_at' => now(),
            'issued_at' => now()->subDay(),
            'document_snapshot' => [
                'applicant_full_name' => $applicant->name,
                'applicant_organization_position' => 'AJK Pemohon',
                'approver_full_name' => $admin->name,
                'approver_organization_position' => 'Pentadbir',
            ],
        ], $voucherOverrides));
        $token = str_pad('publictoken'.$sequence, 64, 'x');
        $acknowledgement = VoucherAcknowledgement::query()->create(array_merge([
            'payment_voucher_id' => $voucher->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ], $acknowledgementOverrides));

        return [$voucher, $acknowledgement, $token, $applicant, $admin];
    }
}
