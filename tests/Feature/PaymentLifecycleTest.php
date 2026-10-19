<?php

namespace Tests\Feature;

use App\Mail\DonationReceiptMail;
use App\Mail\PaymentStatusMail;
use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const TOYYIBPAY_SECRET = 'test-secret-key';

    private const ADMIN_EMAIL = 'admin-notification@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'services.notifications.admin_email' => self::ADMIN_EMAIL,
            'services.toyyibpay.secret_key' => self::TOYYIBPAY_SECRET,
            'services.toyyibpay.category_code' => 'test-category',
            'services.toyyibpay.base_url' => 'https://dev.toyyibpay.test',
        ]);
    }

    public function test_valid_toyyibpay_callback_completes_payment_and_notifies_donor_and_admin(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-123',
            'donated_at' => null,
        ]);

        $response = $this->post(route('payment.callback'), $this->callbackPayload($receipt));

        $response->assertOk()->assertSeeText('OK');
        $receipt->refresh();

        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->status);
        $this->assertSame('gateway-ref-123', $receipt->payment_reference);
        $this->assertNotNull($receipt->verified_at);
        $this->assertNull($receipt->verified_by);
        $this->assertSame(now()->toDateString(), $receipt->donated_at->toDateString());
        $this->assertDatabaseHas('donation_receipt_audits', [
            'donation_receipt_id' => $receipt->id,
            'action' => 'verify',
            'actor_type' => 'toyyibpay',
        ]);

        Mail::assertSent(DonationReceiptMail::class, function (DonationReceiptMail $mail) use ($receipt) {
            return $mail->receipt->is($receipt) && $mail->hasTo($receipt->donor_email);
        });
        Mail::assertSent(PaymentStatusMail::class, function (PaymentStatusMail $mail) use ($receipt) {
            return $mail->receipt->is($receipt)
                && $mail->event === PaymentStatusMail::PAYMENT_COMPLETED
                && $mail->audience === 'admin'
                && $mail->hasTo(self::ADMIN_EMAIL);
        });
    }

    public function test_duplicate_toyyibpay_callback_has_no_duplicate_email_side_effects(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-123',
        ]);
        $payload = $this->callbackPayload($receipt);

        $this->post(route('payment.callback'), $payload)->assertOk();
        $this->post(route('payment.callback'), $payload)->assertOk();

        Mail::assertSent(DonationReceiptMail::class, 1);
        Mail::assertSent(PaymentStatusMail::class, 1);
    }

    public function test_return_url_cannot_mark_an_unverified_payment_completed(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-123',
        ]);

        $this->get(route('payment.return', [
            'status_id' => 1,
            'billcode' => 'bill-123',
            'order_id' => $receipt->receipt_no,
        ]))->assertRedirect(route('public.index'));

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_invalid_toyyibpay_callback_hash_is_rejected(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-123',
        ]);
        $payload = $this->callbackPayload($receipt);
        $payload['hash'] = 'not-a-valid-hash';

        $this->post(route('payment.callback'), $payload)->assertForbidden();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_toyyibpay_callback_never_uses_payment_reference_as_a_billcode_fallback(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'payment_reference' => 'bill-123',
            'toyyibpay_bill_code' => null,
        ]);
        $payload = $this->callbackPayload($receipt);
        $payload['billcode'] = 'bill-123';

        $this->post(route('payment.callback'), $payload)->assertNotFound();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        $this->assertSame('bill-123', $receipt->payment_reference);
        Mail::assertNothingSent();
    }

    public function test_toyyibpay_callback_rejects_an_amount_mismatch(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-123',
        ]);
        $payload = $this->callbackPayload($receipt);
        $payload['amount'] = '49.99';

        $this->post(route('payment.callback'), $payload)->assertUnprocessable();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_toyyibpay_callback_requires_an_exact_billcode(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-correct',
        ]);
        $payload = $this->callbackPayload($receipt);
        $payload['billcode'] = 'bill-wrong';

        $this->post(route('payment.callback'), $payload)->assertNotFound();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_toyyibpay_callback_requires_an_exact_receipt_order_id(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'toyyibpay',
            'toyyibpay_bill_code' => 'bill-123',
        ]);
        $payload = $this->callbackPayload($receipt);
        $payload['order_id'] = 'REC-NOT-THE-ORDER';
        $payload['hash'] = md5(
            self::TOYYIBPAY_SECRET.$payload['status'].$payload['order_id'].$payload['refno'].'ok',
        );

        $this->post(route('payment.callback'), $payload)->assertNotFound();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_legacy_fpx_with_an_exact_billcode_can_complete_without_changing_its_method(): void
    {
        Mail::fake();
        $receipt = $this->makeReceipt([
            'payment_method' => 'fpx',
            'toyyibpay_bill_code' => 'bill-123',
        ]);

        $this->assertFalse($receipt->isManualPayment());
        $this->post(route('payment.callback'), $this->callbackPayload($receipt))->assertOk();

        $receipt->refresh();
        $this->assertSame('fpx', $receipt->payment_method);
        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->status);
        $this->assertSame('gateway-ref-123', $receipt->payment_reference);
        Mail::assertSent(DonationReceiptMail::class, 1);
        Mail::assertSent(PaymentStatusMail::class, 1);
    }

    public function test_manual_bank_qr_and_cash_submissions_are_pending_and_notify_both_parties(): void
    {
        Mail::fake();
        Storage::fake('public');
        $campaign = Campaign::query()->create([
            'title' => 'Kempen Ujian',
            'slug' => 'kempen-ujian',
            'target_amount' => 1000,
            'collected_amount' => 0,
            'is_active' => true,
        ]);

        foreach (['bank_transfer', 'qr_pay', 'cash'] as $index => $method) {
            $reference = strtoupper($method).'-REF-'.$index;
            $payload = [
                'campaign_id' => $campaign->slug,
                'donor_name' => 'Penderma '.$method,
                'donor_email' => $method.'@example.test',
                'donor_phone' => '0191234567',
                'amount' => 25,
                'payment_method' => $method,
                'transaction_reference' => $reference,
                'transaction_date' => '2026-07-16',
                'description' => 'Sumbangan ujian',
            ];

            if ($method === 'qr_pay') {
                $payload['proof_file'] = UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf');
            }

            $this->post(route('public.donate.process'), $payload)
                ->assertRedirect(route('public.show', $campaign->id));

            $receipt = DonationReceipt::query()->where('payment_reference', $reference)->firstOrFail();
            $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->status);
            $this->assertSame($campaign->id, $receipt->campaign_id);

            if ($method === 'qr_pay') {
                $this->assertNotNull($receipt->proof_file);
                Storage::disk('public')->assertExists($receipt->proof_file);
            }

            Mail::assertSent(PaymentStatusMail::class, function (PaymentStatusMail $mail) use ($receipt) {
                return $mail->receipt->is($receipt)
                    && $mail->event === PaymentStatusMail::MANUAL_RECEIVED
                    && $mail->audience === 'donor'
                    && $mail->hasTo($receipt->donor_email);
            });
            Mail::assertSent(PaymentStatusMail::class, function (PaymentStatusMail $mail) use ($receipt) {
                return $mail->receipt->is($receipt)
                    && $mail->event === PaymentStatusMail::MANUAL_RECEIVED
                    && $mail->audience === 'admin'
                    && $mail->hasTo(self::ADMIN_EMAIL);
            });
        }

        Mail::assertSent(PaymentStatusMail::class, 6);
    }

    public function test_admin_can_verify_pending_manual_payment_once(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->makeReceipt(['payment_method' => 'bank_transfer']);

        $this->actingAs($admin)
            ->patch(route('receipts.verify', $receipt))
            ->assertRedirect(route('receipts.index'));

        $receipt->refresh();
        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->status);
        $this->assertSame($admin->id, $receipt->verified_by);
        $this->assertNotNull($receipt->verified_at);

        $this->actingAs($admin)
            ->patch(route('receipts.verify', $receipt))
            ->assertRedirect(route('receipts.index'));

        Mail::assertSent(DonationReceiptMail::class, 1);
        Mail::assertSent(PaymentStatusMail::class, 1);
        Mail::assertSent(DonationReceiptMail::class, fn (DonationReceiptMail $mail) => $mail->hasTo($receipt->donor_email));
        Mail::assertSent(PaymentStatusMail::class, function (PaymentStatusMail $mail) {
            return $mail->event === PaymentStatusMail::PAYMENT_COMPLETED
                && $mail->audience === 'admin'
                && $mail->hasTo(self::ADMIN_EMAIL);
        });
    }

    public function test_admin_can_reject_pending_manual_payment_with_a_reason_once(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->makeReceipt(['payment_method' => 'qr_pay']);

        $this->actingAs($admin)
            ->patch(route('receipts.reject', $receipt), ['rejection_reason' => 'Bukti pembayaran tidak sepadan.'])
            ->assertRedirect(route('receipts.index'));

        $receipt->refresh();
        $this->assertSame(DonationReceipt::STATUS_REJECTED, $receipt->status);
        $this->assertSame('Bukti pembayaran tidak sepadan.', $receipt->rejection_reason);
        $this->assertSame($admin->id, $receipt->verified_by);
        $this->assertNotNull($receipt->verified_at);

        $this->actingAs($admin)
            ->patch(route('receipts.reject', $receipt), ['rejection_reason' => 'Cubaan kedua'])
            ->assertRedirect(route('receipts.index'));

        Mail::assertSent(PaymentStatusMail::class, 2);
        Mail::assertSent(PaymentStatusMail::class, function (PaymentStatusMail $mail) use ($receipt) {
            return $mail->receipt->is($receipt)
                && $mail->event === PaymentStatusMail::PAYMENT_REJECTED
                && $mail->audience === 'donor'
                && $mail->hasTo($receipt->donor_email);
        });
        Mail::assertSent(PaymentStatusMail::class, function (PaymentStatusMail $mail) {
            return $mail->event === PaymentStatusMail::PAYMENT_REJECTED
                && $mail->audience === 'admin'
                && $mail->hasTo(self::ADMIN_EMAIL);
        });
    }

    public function test_regular_user_cannot_verify_or_reject_payment(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'penderma']);
        $receipt = $this->makeReceipt(['payment_method' => 'cash']);

        $this->actingAs($user)
            ->patch(route('receipts.verify', $receipt))
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('receipts.reject', $receipt), ['rejection_reason' => 'Tidak sah'])
            ->assertForbidden();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_status_labels_are_consistent_in_admin_list_detail_pdf_and_email_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([
            DonationReceipt::STATUS_PENDING => 'TUNGGU PENGESAHAN',
            DonationReceipt::STATUS_COMPLETED => 'BERJAYA',
            DonationReceipt::STATUS_REJECTED => 'DITOLAK',
            DonationReceipt::STATUS_FAILED => 'GAGAL',
        ] as $status => $label) {
            $receipt = $this->makeReceipt([
                'status' => $status,
                'rejection_reason' => $status === DonationReceipt::STATUS_REJECTED ? 'Tidak sepadan' : null,
            ]);

            $this->actingAs($admin)
                ->get(route('receipts.show', $receipt))
                ->assertOk()
                ->assertSeeText($label);

            $this->assertStringContainsString(
                $label,
                view('pdf.donation-receipt', compact('receipt'))->render(),
            );
            $this->assertStringContainsString(
                $label,
                view('emails.payment-status', [
                    'receipt' => $receipt,
                    'event' => $status === DonationReceipt::STATUS_REJECTED
                        ? PaymentStatusMail::PAYMENT_REJECTED
                        : PaymentStatusMail::MANUAL_RECEIVED,
                    'audience' => 'admin',
                ])->render(),
            );
            $this->assertStringContainsString(
                $label,
                view('emails.receipt', compact('receipt'))->render(),
            );
        }

        $this->actingAs($admin)
            ->get(route('receipts.index'))
            ->assertOk()
            ->assertSeeText('TUNGGU PENGESAHAN')
            ->assertSeeText('BERJAYA')
            ->assertSeeText('DITOLAK')
            ->assertSeeText('GAGAL');
    }

    public function test_campaign_navigation_receipt_and_payment_routes_remain_registered(): void
    {
        foreach ([
            'public.index',
            'dana.show',
            'dana.information',
            'public.donate.process',
            'payment.return',
            'payment.callback',
            'receipts.index',
            'receipts.show',
            'receipts.verify',
            'receipts.reject',
            'admin.cms.create',
            'admin.cms.edit',
        ] as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), "Route [{$routeName}] is missing.");
        }
    }

    private function makeReceipt(array $overrides = []): DonationReceipt
    {
        return DonationReceipt::query()->create(array_merge([
            'receipt_no' => 'REC-TEST-'.strtoupper(substr(uniqid(), -6)),
            'donor_name' => 'Penderma Ujian',
            'donor_email' => 'donor@example.test',
            'donor_phone' => '0191234567',
            'amount' => 50,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'manual-ref-123',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => now(),
            'description' => 'Sumbangan ujian',
        ], $overrides));
    }

    private function callbackPayload(DonationReceipt $receipt): array
    {
        $status = '1';
        $reference = 'gateway-ref-123';

        return [
            'refno' => $reference,
            'status' => $status,
            'reason' => 'Approved',
            'billcode' => $receipt->toyyibpay_bill_code,
            'order_id' => $receipt->receipt_no,
            'amount' => '50.00',
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'hash' => md5(self::TOYYIBPAY_SECRET.$status.$receipt->receipt_no.$reference.'ok'),
        ];
    }
}
