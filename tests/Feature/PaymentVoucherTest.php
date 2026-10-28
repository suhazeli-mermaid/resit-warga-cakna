<?php

namespace Tests\Feature;

use App\Mail\VoucherNotificationMail;
use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use App\Services\PaymentVoucherWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    public function test_roles_enforce_record_isolation_and_payment_scope(): void
    {
        $owner = User::factory()->create(['role' => 'ajk']);
        $otherAjk = User::factory()->create(['role' => 'ajk']);
        $treasurer = User::factory()->create(['role' => 'bendahari']);
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = $this->voucher($owner);

        $this->get(route('vouchers.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('vouchers.show', $voucher))->assertOk();
        $this->actingAs($otherAjk)->get(route('vouchers.show', $voucher))->assertForbidden();
        $this->actingAs($treasurer)->get(route('vouchers.show', $voucher))->assertForbidden();
        $this->actingAs($admin)->get(route('vouchers.show', $voucher))->assertOk();

        $voucher->update(['status' => PaymentVoucher::ISSUED, 'issued_at' => now()]);
        $this->actingAs($treasurer)->get(route('vouchers.show', $voucher))->assertOk();
    }

    public function test_creation_and_collision_safe_daily_numbering(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);
        $payload = $this->payload(['voucher_date' => '2026-10-28']);

        $this->actingAs($ajk)->post(route('vouchers.store'), $payload)->assertRedirect();
        $this->actingAs($ajk)->post(route('vouchers.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('payment_vouchers', ['voucher_no' => 'B-2026-1028-001']);
        $this->assertDatabaseHas('payment_vouchers', ['voucher_no' => 'B-2026-1028-002']);
        $this->assertDatabaseCount('voucher_number_sequences', 1);
        $this->assertDatabaseHas('payment_voucher_audits', ['event' => 'created', 'actor_id' => $ajk->id]);
    }

    public function test_draft_can_be_edited_but_issued_document_cannot(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);
        $voucher = $this->voucher($ajk);

        $this->actingAs($ajk)->put(route('vouchers.update', $voucher), $this->payload(['purpose' => 'Tujuan dikemas kini']))->assertRedirect();
        $this->assertSame('Tujuan dikemas kini', $voucher->fresh()->purpose);

        $voucher->update(['status' => PaymentVoucher::ISSUED, 'issued_at' => now()]);
        $this->actingAs($ajk)->put(route('vouchers.update', $voucher), $this->payload())->assertForbidden();
    }

    public function test_missing_applicant_signature_blocks_submission_without_server_error(): void
    {
        $applicant = User::factory()->create([
            'role' => 'ajk',
            'organization_position' => 'AJK Kebajikan',
            'can_create_voucher' => true,
        ]);
        $voucher = $this->voucher($applicant);

        $this->actingAs($applicant)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSeeText('Tandatangan Pemohon: ⚠ Belum Dimuat Naik')
            ->assertSeeText('Muat Naik Tandatangan')
            ->assertSee(route('profile.edit').'#digital-signature', false);

        $this->actingAs($applicant)->post(route('vouchers.submit', $voucher))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'applicant_signature' => 'Tandatangan digital anda belum dimuat naik. Sila lengkapkan tandatangan digital di Profil Pengguna sebelum menghantar Baucar untuk kelulusan.',
            ]);

        $this->assertSame(PaymentVoucher::DRAFT, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->submitted_at);
    }

    public function test_applicant_signature_allows_submission(): void
    {
        $applicant = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);
        $this->signature($applicant, 'ready');
        $voucher = $this->voucher($applicant);

        $this->actingAs($applicant)->post(route('vouchers.submit', $voucher))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
        Storage::disk('local')->assertExists($voucher->fresh()->applicant_signature_snapshot_path);
    }

    public function test_missing_approver_signature_is_handled_gracefully(): void
    {
        $applicant = User::factory()->create(['role' => 'ajk']);
        $approver = User::factory()->create(['role' => 'admin', 'organization_position' => 'Pengerusi']);
        $this->signature($applicant, 'applicant');
        $voucher = app(PaymentVoucherWorkflow::class)->submit($this->voucher($applicant), $applicant);

        $this->actingAs($approver)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSeeText('Tandatangan Pelulus: ⚠ Belum Dimuat Naik')
            ->assertSeeText('Muat Naik Tandatangan');
        $this->actingAs($approver)->post(route('vouchers.approve', $voucher))
            ->assertRedirect()
            ->assertSessionHasErrors('approver_signature');

        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->approved_at);
    }

    public function test_missing_official_stamp_is_handled_gracefully(): void
    {
        $applicant = User::factory()->create(['role' => 'ajk']);
        $approver = User::factory()->create(['role' => 'admin']);
        $this->signature($applicant, 'applicant');
        $this->signature($approver, 'approver');
        $workflow = app(PaymentVoucherWorkflow::class);
        $voucher = $workflow->submit($this->voucher($applicant), $applicant);
        $voucher = $workflow->approve($voucher, $approver);

        $this->actingAs($approver)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSeeText('Cop Rasmi: ⚠ Belum Dimuat Naik')
            ->assertSeeText('Tetapan Organisasi');
        $this->actingAs($approver)->post(route('vouchers.issue', $voucher))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'official_stamp' => 'Cop rasmi Warga Cakna belum dimuat naik.',
            ]);

        $this->assertSame(PaymentVoucher::APPROVED, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->issued_at);
    }

    public function test_approval_and_issue_preserve_signature_and_stamp_snapshots(): void
    {
        [$voucher, $applicant, $approver] = $this->issuedVoucher();
        $snapshot = $voucher->document_snapshot;

        $this->assertSame(PaymentVoucher::ISSUED, $voucher->status);
        $this->assertSame($applicant->name, $snapshot['applicant_name']);
        $this->assertSame($approver->name, $snapshot['approver_name']);
        Storage::disk('local')->assertExists($voucher->applicant_signature_snapshot_path);
        Storage::disk('local')->assertExists($voucher->approver_signature_snapshot_path);
        Storage::disk('local')->assertExists($voucher->stamp_snapshot_path);

        $before = Storage::disk('local')->get($voucher->applicant_signature_snapshot_path);
        $oldProfilePath = $applicant->digital_signature_path;
        $this->actingAs($applicant)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->image('replacement.png', 500, 150),
        ])->assertSessionHas('status', 'signature-updated');

        $this->assertNotSame($oldProfilePath, $applicant->fresh()->digital_signature_path);
        Storage::disk('local')->assertMissing($oldProfilePath);
        $this->assertSame($before, Storage::disk('local')->get($voucher->applicant_signature_snapshot_path));
    }

    public function test_issued_voucher_has_browser_and_pdf_documents(): void
    {
        [$voucher, $applicant] = $this->issuedVoucher();

        $this->actingAs($applicant)->get(route('vouchers.document', $voucher))
            ->assertOk()->assertSeeText($voucher->voucher_no)->assertSeeText('BAUCAR BAYARAN');
        $this->actingAs($applicant)->get(route('vouchers.pdf', $voucher))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_payment_confirmation_sends_single_use_acknowledgement_and_completes_audit(): void
    {
        [$voucher, $applicant, $admin] = $this->issuedVoucher();
        $capturedToken = null;

        $this->actingAs($admin)->post(route('vouchers.confirm-payment', $voucher), [
            'paid_amount' => '125.50',
            'transaction_date' => today()->toDateString(),
            'transaction_reference' => 'BANK-TXN-9981',
        ])->assertSessionHas('success');

        $voucher->refresh();
        $this->assertSame(PaymentVoucher::AWAITING_ACKNOWLEDGEMENT, $voucher->status);
        $acknowledgement = $voucher->acknowledgements()->sole();
        $this->assertSame('A-'.substr($voucher->voucher_no, 2), $acknowledgement->acknowledgement_no);
        $this->assertSame('MENUNGGU AKUAN', $acknowledgement->status_label);
        $this->assertDatabaseHas('payment_voucher_audits', ['payment_voucher_id' => $voucher->id, 'event' => 'payment_confirmed']);
        $this->assertDatabaseHas('payment_voucher_audits', ['payment_voucher_id' => $voucher->id, 'event' => 'acknowledgement_sent']);
        Mail::assertSent(VoucherNotificationMail::class, function ($mail) use (&$capturedToken) {
            if ($mail->event === 'payment_confirmed') {
                $capturedToken = $mail->acknowledgementToken;

                return true;
            }

            return false;
        });
        $this->assertNotNull($capturedToken);
        $this->assertDatabaseMissing('voucher_acknowledgements', ['token_hash' => $capturedToken]);

        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index'))
            ->assertOk()
            ->assertSeeText($acknowledgement->acknowledgement_no)
            ->assertSeeText('MENUNGGU AKUAN')
            ->assertSeeText('Papar')
            ->assertSeeText('Lihat Baucar')
            ->assertSeeText('Hantar Semula Pautan');

        $this->get(route('vouchers.acknowledgement.show', $capturedToken))->assertOk()->assertDontSee('BANK-TXN-9981');
        $this->post(route('vouchers.acknowledgement.store', $capturedToken), [
            'recipient_name' => 'Penerima Ujian', 'accepted' => '1', 'notes' => 'Diterima.',
        ])->assertOk()
            ->assertSeeText('SURAT AKUAN PENERIMAAN BAYARAN')
            ->assertSeeText('PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU')
            ->assertSee('/images/logo-warga-cakna.png', false);
        $this->assertSame(PaymentVoucher::COMPLETED, $voucher->fresh()->status);
        $this->assertSame($acknowledgement->id, $voucher->acknowledgements()->sole()->id);
        $this->assertNotNull($acknowledgement->fresh()->used_at);
        $this->get(route('vouchers.acknowledgement.show', $capturedToken))->assertStatus(410);
        $this->assertDatabaseHas('payment_voucher_audits', ['payment_voucher_id' => $voucher->id, 'event' => 'acknowledgement_completed']);
        $this->actingAs($applicant)->get(route('vouchers.acknowledgements.pdf', $acknowledgement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, Mail::sent(VoucherNotificationMail::class, fn ($mail) => $mail->event === 'acknowledgement_completed')->count());
    }

    public function test_duplicate_payment_confirmation_is_idempotent_and_does_not_duplicate_token_or_email(): void
    {
        [$voucher, , $admin] = $this->issuedVoucher();
        $payment = [
            'paid_amount' => '125.50',
            'transaction_date' => today()->toDateString(),
            'transaction_reference' => 'BANK-TXN-IDEMPOTENT',
        ];

        $this->actingAs($admin)->post(route('vouchers.confirm-payment', $voucher), $payment)
            ->assertSessionHas('success');
        $acknowledgement = $voucher->acknowledgements()->sole();
        $tokenHash = $acknowledgement->token_hash;

        $this->actingAs($admin)->post(route('vouchers.confirm-payment', $voucher->fresh()), array_merge($payment, [
            'paid_amount' => '999.00',
            'transaction_reference' => 'MUST-NOT-OVERWRITE',
        ]))->assertSessionHas('success', 'Bayaran telah disahkan dan permintaan Akuan sedia ada kekal aktif. Tiada e-mel pendua dihantar.');

        $this->assertSame(1, $voucher->acknowledgements()->count());
        $this->assertSame($acknowledgement->id, $voucher->acknowledgements()->sole()->id);
        $this->assertSame($tokenHash, $voucher->acknowledgements()->sole()->token_hash);
        $this->assertSame('125.50', $voucher->fresh()->paid_amount);
        $this->assertSame('BANK-TXN-IDEMPOTENT', $voucher->fresh()->transaction_reference);
        $this->assertSame(1, $voucher->audits()->where('event', 'payment_confirmed')->count());
        $this->assertSame(1, Mail::sent(VoucherNotificationMail::class, fn ($mail) => $mail->event === 'payment_confirmed')->count());
    }

    public function test_confirmed_legacy_voucher_without_acknowledgement_is_repaired_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = $this->voucher($admin, [
            'status' => PaymentVoucher::PAYMENT_CONFIRMED,
            'paid_amount' => '125.50',
            'transaction_date' => today(),
            'transaction_reference' => 'LEGACY-PAYMENT',
            'payment_confirmed_by' => $admin->id,
            'payment_confirmed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('vouchers.confirm-payment', $voucher), [
            'paid_amount' => '125.50',
            'transaction_date' => today()->toDateString(),
            'transaction_reference' => 'LEGACY-PAYMENT',
        ])->assertSessionHas('success');

        $this->assertSame(PaymentVoucher::AWAITING_ACKNOWLEDGEMENT, $voucher->fresh()->status);
        $this->assertSame(1, $voucher->acknowledgements()->count());
        Mail::assertSent(VoucherNotificationMail::class, fn ($mail) => $mail->event === 'payment_confirmed');
    }

    public function test_expired_token_is_rejected_without_exposing_voucher(): void
    {
        $voucher = $this->voucher(User::factory()->create(['role' => 'ajk']), ['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]);
        $token = str_repeat('a', 64);
        VoucherAcknowledgement::create([
            'payment_voucher_id' => $voucher->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('vouchers.acknowledgement.show', $token))->assertStatus(410);
        $this->assertNull($voucher->fresh()->completed_at);
    }

    public function test_resending_acknowledgement_invalidates_the_previous_token(): void
    {
        [$voucher, $applicant, $admin] = $this->issuedVoucher();
        $this->actingAs($admin)->post(route('vouchers.confirm-payment', $voucher), [
            'paid_amount' => '125.50',
            'transaction_date' => today()->toDateString(),
            'transaction_reference' => 'BANK-TXN-RESEND',
        ])->assertSessionHas('success');
        $oldToken = Mail::sent(VoucherNotificationMail::class)
            ->first(fn ($mail) => $mail->event === 'payment_confirmed')
            ->acknowledgementToken;
        $acknowledgementId = $voucher->acknowledgements()->sole()->id;

        $this->actingAs($applicant)->get(route('vouchers.acknowledgements.index'))
            ->assertOk()
            ->assertSeeText('Papar')
            ->assertSeeText('Lihat Baucar')
            ->assertSeeText('Hantar Semula Pautan');

        $this->actingAs($applicant)->post(route('vouchers.resend-acknowledgement', $voucher->fresh()))
            ->assertSessionHas('success');
        $newToken = Mail::sent(VoucherNotificationMail::class)
            ->filter(fn ($mail) => $mail->event === 'payment_confirmed')
            ->last()
            ->acknowledgementToken;

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(1, $voucher->acknowledgements()->count());
        $this->assertSame($acknowledgementId, $voucher->acknowledgements()->sole()->id);
        $this->get(route('vouchers.acknowledgement.show', $oldToken))->assertNotFound();
        $this->get(route('vouchers.acknowledgement.show', $newToken))->assertOk();
        $this->assertDatabaseHas('payment_voucher_audits', [
            'payment_voucher_id' => $voucher->id,
            'event' => 'acknowledgement_link_regenerated',
        ]);

        $unrelatedAjk = User::factory()->create(['role' => 'ajk']);
        $this->actingAs($unrelatedAjk)->post(route('vouchers.resend-acknowledgement', $voucher->fresh()))
            ->assertForbidden();
    }

    public function test_completed_financial_record_is_archived_not_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = $this->voucher($admin, ['status' => PaymentVoucher::COMPLETED, 'completed_at' => now()]);

        $this->actingAs($admin)->post(route('vouchers.archive', $voucher), ['reason' => 'Tutup tahun kewangan'])->assertRedirect();
        $this->assertDatabaseHas('payment_vouchers', ['id' => $voucher->id, 'status' => PaymentVoucher::ARCHIVED]);
        $this->assertDatabaseHas('payment_voucher_audits', ['payment_voucher_id' => $voucher->id, 'event' => 'archived']);
        $this->assertNull($voucher->fresh()->getAttribute('deleted_at'));
    }

    public function test_dashboard_voucher_acknowledgement_and_archive_lists_are_separate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = $this->voucher($admin, ['recipient_name' => 'Penerima Aktif']);
        $withAcknowledgement = $this->voucher($admin, [
            'status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
            'recipient_name' => 'Penerima Akuan',
            'paid_amount' => '125.50',
            'payment_confirmed_at' => now(),
        ]);
        $acknowledgement = $this->acknowledgement($withAcknowledgement);
        $archived = $this->voucher($admin, [
            'status' => PaymentVoucher::ARCHIVED,
            'recipient_name' => 'Penerima Arkib',
            'archived_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('vouchers.dashboard'))
            ->assertOk()
            ->assertSeeText('LIHAT SENARAI BAUCAR')
            ->assertSeeText('LIHAT SURAT AKUAN');

        $this->actingAs($admin)->get(route('vouchers.index'))
            ->assertOk()
            ->assertSeeText($active->voucher_no)
            ->assertSeeText($withAcknowledgement->voucher_no)
            ->assertDontSeeText($acknowledgement->acknowledgement_no)
            ->assertDontSeeText($archived->voucher_no);

        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index'))
            ->assertOk()
            ->assertSeeText($acknowledgement->acknowledgement_no)
            ->assertSeeText($withAcknowledgement->voucher_no)
            ->assertDontSeeText($active->voucher_no)
            ->assertDontSeeText($archived->voucher_no);

        $this->actingAs($admin)->get(route('vouchers.archive.index'))
            ->assertOk()
            ->assertSeeText($archived->voucher_no)
            ->assertDontSeeText($active->voucher_no);
    }

    public function test_acknowledgement_admin_listing_and_ajk_record_isolation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'ajk']);
        $otherAjk = User::factory()->create(['role' => 'ajk']);
        $recipient = User::factory()->create(['role' => 'penderma']);
        $ownAcknowledgement = $this->acknowledgement($this->voucher($owner, ['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]));
        $otherAcknowledgement = $this->acknowledgement($this->voucher($otherAjk, ['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]));

        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index'))
            ->assertOk()
            ->assertSeeText($ownAcknowledgement->acknowledgement_no)
            ->assertSeeText($otherAcknowledgement->acknowledgement_no);

        $this->actingAs($owner)->get(route('vouchers.acknowledgements.index'))
            ->assertOk()
            ->assertSeeText($ownAcknowledgement->acknowledgement_no)
            ->assertDontSeeText($otherAcknowledgement->acknowledgement_no);
        $this->actingAs($owner)->get(route('vouchers.acknowledgements.show', $otherAcknowledgement))->assertForbidden();
        $this->actingAs($recipient)->get(route('vouchers.acknowledgements.index'))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('vouchers.acknowledgements.index'))->assertRedirect(route('login'));
    }

    public function test_voucher_and_acknowledgement_have_authorized_bidirectional_navigation(): void
    {
        $owner = User::factory()->create(['role' => 'ajk']);
        $voucher = $this->voucher($owner, ['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]);
        $acknowledgement = $this->acknowledgement($voucher);

        $this->actingAs($owner)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSee(route('vouchers.acknowledgements.show', $acknowledgement), false)
            ->assertSeeText('Lihat Surat Akuan');

        $this->actingAs($owner)->get(route('vouchers.acknowledgements.show', $acknowledgement))
            ->assertOk()
            ->assertSee(route('vouchers.show', $voucher), false)
            ->assertSeeText('Lihat Baucar');
    }

    public function test_acknowledgement_status_filters_and_completed_pdf_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $this->acknowledgement($this->voucher($admin, ['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]));
        $expired = $this->acknowledgement(
            $this->voucher($admin, ['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]),
            ['expires_at' => now()->subMinute()],
        );
        $completedVoucher = $this->voucher($admin, [
            'status' => PaymentVoucher::COMPLETED,
            'paid_amount' => '125.50',
            'transaction_date' => today(),
            'payment_confirmed_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
        $completed = $this->acknowledgement($completedVoucher, [
            'used_at' => now(),
            'recipient_name' => 'Penerima Ujian',
        ]);

        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index', ['status' => 'pending']))
            ->assertOk()->assertSeeText($pending->acknowledgement_no)->assertDontSeeText($expired->acknowledgement_no)->assertDontSeeText($completed->acknowledgement_no);
        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index', ['status' => 'expired']))
            ->assertOk()->assertSeeText('TAMAT TEMPOH')->assertSeeText($expired->acknowledgement_no)->assertDontSeeText($pending->acknowledgement_no);
        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index', ['status' => 'completed']))
            ->assertOk()->assertSeeText('SELESAI')->assertSeeText($completed->acknowledgement_no)->assertSeeText('PDF Surat Akuan');

        $this->actingAs($admin)->get(route('vouchers.acknowledgements.pdf', $pending))->assertStatus(409);
        $this->actingAs($admin)->get(route('vouchers.acknowledgements.pdf', $completed))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_voucher_and_acknowledgement_search_and_date_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $namedApplicant = User::factory()->create(['role' => 'ajk', 'name' => 'Pemohon Khas UAT']);
        $matching = $this->voucher($namedApplicant, [
            'voucher_date' => '2026-10-28',
            'recipient_name' => 'Penerima Carian UAT',
            'status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
        ]);
        $acknowledgement = $this->acknowledgement($matching, ['created_at' => '2026-10-28 10:00:00']);
        $other = $this->voucher($admin, ['voucher_date' => '2026-09-01', 'recipient_name' => 'Penerima Lain']);

        $this->actingAs($admin)->get(route('vouchers.index', ['search' => 'Pemohon Khas UAT']))
            ->assertOk()->assertSeeText($matching->voucher_no)->assertDontSeeText($other->voucher_no);
        $this->actingAs($admin)->get(route('vouchers.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertOk()->assertSeeText($matching->voucher_no)->assertDontSeeText($other->voucher_no);
        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index', ['search' => $acknowledgement->acknowledgement_no]))
            ->assertOk()->assertSeeText($acknowledgement->acknowledgement_no)->assertSeeText('Penerima Carian UAT');
        $this->actingAs($admin)->get(route('vouchers.acknowledgements.index', ['date_from' => '2026-10-28', 'date_to' => '2026-10-28']))
            ->assertOk()->assertSeeText($acknowledgement->acknowledgement_no);
    }

    public function test_cancel_and_reissue_preserve_old_document_and_create_new_number(): void
    {
        [$voucher, , $admin] = $this->issuedVoucher();
        $oldSnapshot = $voucher->document_snapshot;
        if ($voucher->voucher_date->isSameDay(today())) {
            DB::table('voucher_number_sequences')->updateOrInsert(
                ['voucher_date' => today()->toDateString()],
                ['next_number' => 2, 'created_at' => now(), 'updated_at' => now()],
            );
        }
        $this->actingAs($admin)->post(route('vouchers.cancel', $voucher), ['reason' => 'Pembetulan penerima'])->assertRedirect();
        $response = $this->actingAs($admin)->post(route('vouchers.reissue', $voucher));
        $response->assertRedirect();
        $new = PaymentVoucher::query()->where('reissued_from_id', $voucher->id)->firstOrFail();

        $response->assertRedirect(route('vouchers.edit', $new));
        $this->assertNotSame($voucher->voucher_no, $new->voucher_no);
        $this->assertSame($oldSnapshot, $voucher->fresh()->document_snapshot);
        $this->assertDatabaseHas('payment_voucher_audits', ['payment_voucher_id' => $voucher->id, 'event' => 'reissued']);
    }

    public function test_signature_upload_rejects_executable_and_accepts_image_privately(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk']);
        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->create('signature.php', 2, 'application/x-php'),
        ])->assertSessionHasErrors('digital_signature');

        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->image('signature.png', 400, 120),
        ])->assertSessionHas('status', 'signature-updated');
        Storage::disk('local')->assertExists($ajk->fresh()->digital_signature_path);
        $this->actingAs($ajk)->get(route('profile.signature.show'))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
    }

    public function test_user_can_only_upload_signature_to_their_own_profile(): void
    {
        $user = User::factory()->create(['role' => 'ajk']);
        $otherUser = User::factory()->create(['role' => 'ajk']);

        $this->actingAs($user)->put(route('profile.signature.update'), [
            'user_id' => $otherUser->id,
            'digital_signature' => UploadedFile::fake()->image('own-signature.jpg', 400, 120),
        ])->assertSessionHas('status', 'signature-updated');

        $this->assertTrue($user->fresh()->hasDigitalSignature());
        $this->assertFalse($otherUser->fresh()->hasDigitalSignature());
    }

    public function test_only_admin_can_manage_and_preview_official_stamp(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($ajk)->put(route('admin.organization.stamp'), [
            'official_stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        ])->assertSessionHas('success');
        $this->actingAs($admin)->get(route('admin.organization.stamp.show'))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
    }

    private function issuedVoucher(): array
    {
        $applicant = User::factory()->create(['role' => 'ajk']);
        $approver = User::factory()->create(['role' => 'admin']);
        $this->signature($applicant, 'applicant');
        $this->signature($approver, 'approver');
        Storage::disk('local')->put('organization/stamps/current.png', $this->png());
        Setting::create(['key' => 'organization_official_stamp_path', 'value' => 'organization/stamps/current.png']);
        $voucher = $this->voucher($applicant);
        $workflow = app(PaymentVoucherWorkflow::class);
        $voucher = $workflow->submit($voucher, $applicant);
        $voucher = $workflow->approve($voucher, $approver);
        $voucher = $workflow->issue($voucher, $approver);

        return [$voucher, $applicant, $approver];
    }

    private function signature(User $user, string $name): void
    {
        $path = "users/{$user->id}/signatures/{$name}.png";
        Storage::disk('local')->put($path, $this->png());
        $user->update([
            'organization_position' => $user->organization_position ?: ($user->isAdmin() ? 'Pengerusi' : 'AJK Kebajikan'),
            'digital_signature_path' => $path,
            'digital_signature_checksum' => hash('sha256', $this->png()),
            'digital_signature_uploaded_at' => now(),
        ]);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function voucher(User $applicant, array $overrides = []): PaymentVoucher
    {
        return PaymentVoucher::create(array_merge([
            'voucher_no' => 'B-2026-1028-'.str_pad((string) (PaymentVoucher::count() + 1), 3, '0', STR_PAD_LEFT),
            'voucher_date' => '2026-10-28', 'status' => PaymentVoucher::DRAFT,
            'applicant_id' => $applicant->id, 'recipient_name' => 'Penerima Ujian',
            'recipient_phone' => '0191234567', 'recipient_email' => 'recipient@example.test',
            'purpose' => 'Bantuan komuniti', 'amount' => '125.50',
        ], $overrides));
    }

    private function acknowledgement(PaymentVoucher $voucher, array $overrides = []): VoucherAcknowledgement
    {
        $attributes = array_merge([
            'payment_voucher_id' => $voucher->id,
            'token_hash' => hash('sha256', $voucher->voucher_no.'-uat'),
            'expires_at' => now()->addDay(),
        ], $overrides);
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $acknowledgement = VoucherAcknowledgement::create($attributes);
        if ($createdAt) {
            $acknowledgement->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $acknowledgement;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'voucher_date' => '2026-10-28', 'recipient_name' => 'Penerima Ujian',
            'recipient_phone' => '0191234567', 'recipient_email' => 'recipient@example.test',
            'purpose' => 'Bantuan komuniti', 'amount' => '125.50',
        ], $overrides);
    }
}
