<?php

namespace Tests\Feature;

use App\Mail\DonationReceiptMail;
use App\Mail\VoucherNotificationMail;
use App\Models\DonationReceipt;
use App\Models\EmailLog;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use App\Services\Email\EmailDeliveryService;
use App\Services\PaymentNotificationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_successful_mail_creates_sent_log_without_message_body_or_token(): void
    {
        Mail::fake();
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);

        $sent = app(EmailDeliveryService::class)->sendMailable(
            $receipt->donor_email,
            $receipt->donor_name,
            new DonationReceiptMail($receipt),
            EmailLog::TYPE_DONATION_RECEIPT,
            EmailLog::RELATED_RECEIPT,
            $receipt->id,
            $receipt->receipt_no,
        );

        $this->assertTrue($sent);
        $log = EmailLog::query()->sole();
        $this->assertSame(EmailLog::SENT, $log->status);
        $this->assertNotNull($log->attempted_at);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->failure_message);
        $this->assertArrayNotHasKey('body', $log->getAttributes());
        $this->assertArrayNotHasKey('token', $log->getAttributes());
        Mail::assertSent(DonationReceiptMail::class, 1);
    }

    public function test_failed_mail_creates_failed_log_and_sanitizes_secrets(): void
    {
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);
        Mail::shouldReceive('to')->once()->with($receipt->donor_email)
            ->andThrow(new RuntimeException('SMTP password=super-secret token=raw-token-value'));

        $sent = app(EmailDeliveryService::class)->sendMailable(
            $receipt->donor_email,
            $receipt->donor_name,
            new DonationReceiptMail($receipt),
            EmailLog::TYPE_DONATION_RECEIPT,
            EmailLog::RELATED_RECEIPT,
            $receipt->id,
            $receipt->receipt_no,
        );

        $this->assertFalse($sent);
        $log = EmailLog::query()->sole();
        $this->assertSame(EmailLog::FAILED, $log->status);
        $this->assertStringNotContainsString('super-secret', $log->failure_message);
        $this->assertStringNotContainsString('raw-token-value', $log->failure_message);
        $this->assertStringContainsString('[REDACTED]', $log->failure_message);
    }

    public function test_only_admin_can_view_email_logs_and_mobile_listing_is_present(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'ajk']);
        $log = $this->log(['recipient_name' => 'Penerima Audit']);

        $this->actingAs($admin)->get(route('admin.email-logs.index'))
            ->assertOk()
            ->assertSeeText('Penerima Audit')
            ->assertSee('md:hidden', false)
            ->assertSeeText('bukan bukti e-mel telah dibaca');
        $this->actingAs($admin)->get(route('admin.email-logs.show', $log))->assertOk();
        $this->actingAs($user)->get(route('admin.email-logs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.email-logs.show', $log))->assertForbidden();
        $this->actingAs($user)->post(route('admin.email-logs.resend', $log), ['confirmed' => '1'])->assertForbidden();
    }

    public function test_search_status_type_and_date_filters_are_applied(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matching = $this->log([
            'recipient_name' => 'Penerima Khas UAT',
            'recipient_email' => 'khas@example.test',
            'related_reference' => 'REC-CARIAN-UAT',
            'created_at' => '2026-10-28 10:00:00',
        ]);
        $other = $this->log([
            'recipient_name' => 'Penerima Lain',
            'recipient_email' => 'lain@example.test',
            'status' => EmailLog::FAILED,
            'mail_type' => EmailLog::TYPE_PASSWORD_RESET,
            'created_at' => '2026-09-01 10:00:00',
        ]);

        $this->actingAs($admin)->get(route('admin.email-logs.index', ['search' => 'REC-CARIAN-UAT']))
            ->assertOk()->assertSeeText($matching->recipient_email)->assertDontSeeText($other->recipient_email);
        $this->actingAs($admin)->get(route('admin.email-logs.index', [
            'status' => EmailLog::SENT,
            'mail_type' => EmailLog::TYPE_DONATION_RECEIPT,
            'date_from' => '2026-10-28',
            'date_to' => '2026-10-28',
        ]))->assertOk()->assertSeeText($matching->recipient_email)->assertDontSeeText($other->recipient_email);
    }

    public function test_ordinary_resend_uses_authoritative_receipt_and_links_new_log(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);
        $original = $this->receiptLog($receipt);

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('success');

        $resent = $original->resends()->sole();
        $this->assertSame(EmailLog::SENT, $resent->status);
        $this->assertSame($admin->id, $resent->resent_by);
        $this->assertSame($receipt->donor_email, $resent->recipient_email);
        Mail::assertSent(DonationReceiptMail::class, 1);
    }

    public function test_failed_resend_is_recorded_without_changing_payment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);
        $original = $this->receiptLog($receipt);
        Mail::shouldReceive('to')->once()->with($receipt->donor_email)
            ->andThrow(new RuntimeException('SMTP secret=must-not-survive'));

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('error');

        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->fresh()->status);
        $resent = $original->resends()->sole();
        $this->assertSame(EmailLog::FAILED, $resent->status);
        $this->assertStringNotContainsString('must-not-survive', $resent->failure_message);
    }

    public function test_acknowledgement_resend_rotates_token_through_workflow(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = $this->voucher($admin);
        $acknowledgement = $this->acknowledgement($voucher);
        $oldHash = $acknowledgement->token_hash;
        $original = $this->log([
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_REQUEST,
            'recipient_name' => $voucher->recipient_name,
            'recipient_email' => $voucher->recipient_email,
            'related_type' => EmailLog::RELATED_ACKNOWLEDGEMENT,
            'related_id' => $acknowledgement->id,
            'related_reference' => $acknowledgement->acknowledgement_no,
        ]);

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('success');

        $this->assertNotSame($oldHash, $acknowledgement->fresh()->token_hash);
        $this->assertSame(1, $voucher->acknowledgements()->count());
        $this->assertDatabaseHas('email_logs', [
            'resend_of_id' => $original->id,
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_REQUEST,
            'status' => EmailLog::SENT,
        ]);
        Mail::assertSent(VoucherNotificationMail::class, fn ($mail) => $mail->event === 'payment_confirmed');
    }

    public function test_used_acknowledgement_token_is_not_reused_or_rotated(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = $this->voucher($admin, ['status' => PaymentVoucher::COMPLETED]);
        $acknowledgement = $this->acknowledgement($voucher, ['used_at' => now()]);
        $oldHash = $acknowledgement->token_hash;
        $original = $this->log([
            'mail_type' => EmailLog::TYPE_ACKNOWLEDGEMENT_REQUEST,
            'related_type' => EmailLog::RELATED_ACKNOWLEDGEMENT,
            'related_id' => $acknowledgement->id,
            'related_reference' => $acknowledgement->acknowledgement_no,
        ]);

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('error');

        $this->assertSame($oldHash, $acknowledgement->fresh()->token_hash);
        $this->assertSame(0, $original->resends()->count());
        Mail::assertNothingSent();
    }

    public function test_password_reset_resend_uses_broker_and_never_persists_raw_token(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $original = $this->log([
            'mail_type' => EmailLog::TYPE_PASSWORD_RESET,
            'recipient_name' => $user->name,
            'recipient_email' => $user->email,
            'related_type' => EmailLog::RELATED_USER,
            'related_id' => $user->id,
            'related_reference' => $user->email,
        ]);
        $rawToken = null;

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$rawToken) {
            $rawToken = $notification->token;

            return true;
        });
        $resent = $original->resends()->sole();
        $this->assertSame($user->email, $resent->recipient_email);
        $this->assertSame($admin->id, $resent->resent_by);
        $this->assertNotNull($rawToken);
        $this->assertStringNotContainsString($rawToken, json_encode($resent->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_normal_password_reset_request_creates_sent_log_without_raw_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $rawToken = null;

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$rawToken) {
            $rawToken = $notification->token;

            return true;
        });
        $log = EmailLog::query()->where('mail_type', EmailLog::TYPE_PASSWORD_RESET)->sole();
        $this->assertSame(EmailLog::SENT, $log->status);
        $this->assertSame($user->email, $log->recipient_email);
        $this->assertStringNotContainsString($rawToken, json_encode($log->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_billplz_success_notification_is_logged_without_replaying_payment(): void
    {
        Mail::fake();
        $receipt = $this->receipt([
            'status' => DonationReceipt::STATUS_COMPLETED,
            'payment_method' => DonationReceipt::GATEWAY_BILLPLZ,
            'payment_gateway' => DonationReceipt::GATEWAY_BILLPLZ,
            'gateway_bill_id' => 'bill-email-log-test',
        ]);

        app(PaymentNotificationService::class)->completed($receipt);

        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->fresh()->status);
        $this->assertDatabaseHas('email_logs', [
            'mail_type' => EmailLog::TYPE_DONATION_RECEIPT,
            'related_id' => $receipt->id,
            'status' => EmailLog::SENT,
        ]);
        Mail::assertSent(DonationReceiptMail::class, 1);
    }

    public function test_completed_payment_remains_completed_when_initial_mail_transport_fails(): void
    {
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);
        Mail::shouldReceive('to')->once()->with($receipt->donor_email)
            ->andThrow(new RuntimeException('SMTP unavailable'));

        app(PaymentNotificationService::class)->completed($receipt);

        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->fresh()->status);
        $this->assertDatabaseHas('email_logs', [
            'mail_type' => EmailLog::TYPE_DONATION_RECEIPT,
            'related_id' => $receipt->id,
            'status' => EmailLog::FAILED,
        ]);
    }

    public function test_email_verification_notification_is_logged_and_can_be_regenerated(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class, 1);
        $original = EmailLog::query()->where('mail_type', EmailLog::TYPE_EMAIL_VERIFICATION)->sole();
        $this->assertSame(EmailLog::SENT, $original->status);

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('success');
        Notification::assertSentTo($user, VerifyEmail::class, 2);
        $this->assertSame(1, $original->resends()->count());
    }

    public function test_duplicate_resend_click_is_blocked(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);
        $original = $this->receiptLog($receipt);

        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), ['confirmed' => '1'])
            ->assertSessionHas('error', 'Permintaan hantar semula baru sahaja diproses. Sila tunggu sebelum mencuba lagi.');

        $this->assertSame(1, $original->resends()->count());
        Mail::assertSent(DonationReceiptMail::class, 1);
    }

    public function test_related_record_link_is_rechecked_and_recipient_cannot_be_overridden(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);
        $original = $this->receiptLog($receipt);

        $this->actingAs($admin)->get(route('admin.email-logs.show', $original))
            ->assertOk()
            ->assertSee(route('receipts.show', $receipt), false)
            ->assertSeeText('Lihat Resit');
        $this->actingAs($admin)->post(route('admin.email-logs.resend', $original), [
            'confirmed' => '1',
            'recipient_email' => 'attacker@example.test',
        ])->assertSessionHas('success');

        $this->assertSame($receipt->donor_email, $original->resends()->sole()->recipient_email);
        $this->assertDatabaseMissing('email_logs', ['recipient_email' => 'attacker@example.test']);
    }

    private function receipt(array $overrides = []): DonationReceipt
    {
        return DonationReceipt::query()->create(array_merge([
            'receipt_no' => 'REC-EMAIL-'.strtoupper(substr(uniqid(), -6)),
            'donor_name' => 'Penderma E-mel',
            'donor_email' => 'donor@example.test',
            'donor_phone' => '0191234567',
            'amount' => 50,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'manual-email-ref',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => now(),
            'description' => 'Sumbangan ujian e-mel',
        ], $overrides));
    }

    private function receiptLog(DonationReceipt $receipt): EmailLog
    {
        return $this->log([
            'recipient_name' => $receipt->donor_name,
            'recipient_email' => $receipt->donor_email,
            'related_type' => EmailLog::RELATED_RECEIPT,
            'related_id' => $receipt->id,
            'related_reference' => $receipt->receipt_no,
        ]);
    }

    private function log(array $overrides = []): EmailLog
    {
        return EmailLog::query()->create(array_merge([
            'mail_type' => EmailLog::TYPE_DONATION_RECEIPT,
            'recipient_name' => 'Penerima Log',
            'recipient_email' => 'recipient@example.test',
            'subject' => 'Subjek Audit E-mel',
            'status' => EmailLog::SENT,
            'attempted_at' => now(),
            'sent_at' => now(),
        ], $overrides));
    }

    private function voucher(User $applicant, array $overrides = []): PaymentVoucher
    {
        return PaymentVoucher::query()->create(array_merge([
            'voucher_no' => 'B-2026-1028-'.str_pad((string) (PaymentVoucher::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'voucher_date' => '2026-10-28',
            'status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
            'applicant_id' => $applicant->id,
            'recipient_name' => 'Penerima Akuan',
            'recipient_phone' => '0191234567',
            'recipient_email' => 'recipient@example.test',
            'purpose' => 'Bantuan komuniti',
            'amount' => '125.50',
            'paid_amount' => '125.50',
            'transaction_date' => today(),
            'payment_confirmed_at' => now(),
        ], $overrides));
    }

    private function acknowledgement(PaymentVoucher $voucher, array $overrides = []): VoucherAcknowledgement
    {
        return VoucherAcknowledgement::query()->create(array_merge([
            'payment_voucher_id' => $voucher->id,
            'token_hash' => hash('sha256', $voucher->voucher_no.'-original-token'),
            'expires_at' => now()->addDay(),
        ], $overrides));
    }
}
