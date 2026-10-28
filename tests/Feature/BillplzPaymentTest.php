<?php

namespace Tests\Feature;

use App\Mail\DonationReceiptMail;
use App\Mail\PaymentStatusMail;
use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Services\Billplz\BillplzPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillplzPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const API_SECRET = 'billplz-test-api-secret';

    private const SIGNATURE_KEY = 'billplz-test-x-signature';

    private const COLLECTION_ID = 'test-collection';

    private const BILL_ID = 'bill-abc123';

    private const ADMIN_EMAIL = 'admin-notification@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'mail.default' => 'array',
            'services.notifications.admin_email' => self::ADMIN_EMAIL,
            'services.billplz.api_secret_key' => self::API_SECRET,
            'services.billplz.collection_id' => self::COLLECTION_ID,
            'services.billplz.x_signature_key' => self::SIGNATURE_KEY,
            'services.billplz.base_url' => 'https://www.billplz-sandbox.com',
            'services.billplz.timeout' => 5,
        ]);
    }

    #[Test]
    public function it_creates_a_bill_with_basic_auth_reference_and_integer_sen_before_redirecting(): void
    {
        Http::fake([
            'https://www.billplz-sandbox.com/api/v3/bills' => Http::response([
                'id' => self::BILL_ID,
                'collection_id' => self::COLLECTION_ID,
                'url' => 'https://www.billplz-sandbox.com/bills/'.self::BILL_ID,
            ]),
        ]);

        $campaign = $this->campaign();
        $response = $this->post(route('public.donate.process'), $this->donationPayload($campaign, '123.45'));

        $response->assertRedirect('https://www.billplz-sandbox.com/bills/'.self::BILL_ID);

        $receipt = DonationReceipt::query()->sole();
        $this->assertSame(DonationReceipt::GATEWAY_BILLPLZ, $receipt->payment_method);
        $this->assertSame(DonationReceipt::GATEWAY_BILLPLZ, $receipt->payment_gateway);
        $this->assertSame(self::BILL_ID, $receipt->gateway_bill_id);
        $this->assertSame(self::COLLECTION_ID, $receipt->gateway_collection_id);
        $this->assertNull($receipt->toyyibpay_bill_code);
        $this->assertNull($receipt->donated_at);

        Http::assertSent(function (ClientRequest $request) use ($receipt) {
            return $request->url() === 'https://www.billplz-sandbox.com/api/v3/bills'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::API_SECRET.':'))
                && (int) $request['amount'] === 12345
                && $request['collection_id'] === self::COLLECTION_ID
                && $request['reference_1_label'] === 'Receipt No'
                && $request['reference_1'] === $receipt->receipt_no
                && $request['callback_url'] === route('billplz.callback')
                && $request['redirect_url'] === route('billplz.redirect');
        });
    }

    #[Test]
    public function rm_amounts_are_converted_to_integer_sen_without_float_truncation(): void
    {
        $service = app(BillplzPaymentService::class);

        $this->assertSame(100, $service->amountToSen('1.00'));
        $this->assertSame(1001, $service->amountToSen('10.01'));
        $this->assertSame(12345, $service->amountToSen('123.45'));
    }

    #[Test]
    public function an_api_failure_is_handled_without_completing_or_faking_a_bill(): void
    {
        Http::fake([
            'https://www.billplz-sandbox.com/api/v3/bills' => Http::response(['error' => ['message' => 'Rejected']], 422),
        ]);

        $campaign = $this->campaign();
        $this->from(route('public.show', $campaign->id))
            ->post(route('public.donate.process'), $this->donationPayload($campaign))
            ->assertRedirect(route('public.show', $campaign->id))
            ->assertSessionHas('error');

        $receipt = DonationReceipt::query()->sole();
        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->status);
        $this->assertNull($receipt->gateway_bill_id);
        $this->assertNull($receipt->gateway_collection_id);
    }

    #[Test]
    public function the_same_public_form_submission_creates_only_one_bill_and_receipt(): void
    {
        Http::fake([
            'https://www.billplz-sandbox.com/api/v3/bills' => Http::response([
                'id' => self::BILL_ID,
                'collection_id' => self::COLLECTION_ID,
                'url' => 'https://www.billplz-sandbox.com/bills/'.self::BILL_ID,
            ]),
        ]);

        $campaign = $this->campaign();
        $form = $this->get(route('public.show', $campaign->id))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="submission_token" value="([^"]+)"/', $form, $matches));
        $this->assertStringContainsString('submitting: false', $form);
        $payload = $this->donationPayload($campaign);
        $payload['submission_token'] = $matches[1];

        $this->post(route('public.donate.process'), $payload)
            ->assertRedirect('https://www.billplz-sandbox.com/bills/'.self::BILL_ID);
        $this->from(route('public.show', $campaign->id))
            ->post(route('public.donate.process'), $payload)
            ->assertRedirect(route('public.show', $campaign->id))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('donation_receipts', 1);
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_valid_signed_callback_completes_payment_and_increments_campaign_once(): void
    {
        Mail::fake();
        $campaign = $this->campaign('10.50');
        $receipt = $this->billplzReceipt($campaign);

        $this->post(route('billplz.callback'), $this->callbackPayload())->assertOk()->assertSeeText('OK');

        $receipt->refresh();
        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->status);
        $this->assertSame('TXN-12345', $receipt->payment_reference);
        $this->assertSame('2026-10-28', $receipt->donated_at?->toDateString());
        $this->assertSame(60.50, (float) $campaign->fresh()->collected_amount);
        $this->assertDatabaseHas('donation_receipt_audits', [
            'donation_receipt_id' => $receipt->id,
            'action' => 'verify',
            'actor_type' => DonationReceipt::GATEWAY_BILLPLZ,
        ]);
        Mail::assertSent(DonationReceiptMail::class, 1);
        Mail::assertSent(PaymentStatusMail::class, 1);
    }

    #[Test]
    public function an_invalid_x_signature_is_rejected(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);
        $payload = $this->callbackPayload();
        $payload['x_signature'] = str_repeat('0', 64);

        $this->post(route('billplz.callback'), $payload)->assertForbidden();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        $this->assertSame(0.0, (float) $campaign->fresh()->collected_amount);
        Mail::assertNothingSent();
    }

    #[Test]
    public function an_unknown_bill_id_is_rejected_even_with_a_valid_signature(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);
        $payload = $this->callbackPayload(['id' => 'unknown-bill']);

        $this->post(route('billplz.callback'), $payload)->assertNotFound();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_wrong_collection_is_rejected(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);

        $this->post(route('billplz.callback'), $this->callbackPayload(['collection_id' => 'wrong-collection']))
            ->assertUnprocessable();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_wrong_bill_amount_is_rejected(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);

        $this->post(route('billplz.callback'), $this->callbackPayload([
            'amount' => '4999',
            'paid_amount' => '4999',
        ]))->assertUnprocessable();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function an_unpaid_or_failed_callback_is_acknowledged_without_completing_payment(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);

        $this->post(route('billplz.callback'), $this->callbackPayload([
            'paid' => 'false',
            'state' => 'due',
            'paid_amount' => '0',
            'transaction_status' => 'failed',
        ]))->assertOk();

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        $this->assertSame(0.0, (float) $campaign->fresh()->collected_amount);
        Mail::assertNothingSent();
    }

    #[Test]
    public function duplicate_callbacks_do_not_repeat_campaign_or_notification_side_effects(): void
    {
        Mail::fake();
        $campaign = $this->campaign('5.00');
        $receipt = $this->billplzReceipt($campaign);
        $payload = $this->callbackPayload();

        $this->post(route('billplz.callback'), $payload)->assertOk();
        $this->post(route('billplz.callback'), $payload)->assertOk();

        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->fresh()->status);
        $this->assertSame(55.0, (float) $campaign->fresh()->collected_amount);
        $this->assertDatabaseCount('donation_receipt_audits', 1);
        Mail::assertSent(DonationReceiptMail::class, 1);
        Mail::assertSent(PaymentStatusMail::class, 1);
    }

    #[Test]
    public function redirect_before_callback_never_completes_the_receipt(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);

        $this->get($this->signedRedirectUrl('true'))
            ->assertRedirect(route('public.index'))
            ->assertSessionHas('success');

        $this->assertSame(DonationReceipt::STATUS_PENDING, $receipt->fresh()->status);
        $this->assertSame(0.0, (float) $campaign->fresh()->collected_amount);
        Mail::assertNothingSent();

        $this->post(route('billplz.callback'), $this->callbackPayload())->assertOk();
        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->fresh()->status);
    }

    #[Test]
    public function callback_before_redirect_shows_the_authoritative_completed_state(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $receipt = $this->billplzReceipt($campaign);

        $this->post(route('billplz.callback'), $this->callbackPayload())->assertOk();
        $this->get($this->signedRedirectUrl('true'))
            ->assertRedirect(route('public.index'))
            ->assertSessionHas('success', 'Pembayaran sumbangan anda telah disahkan BERJAYA.');

        $this->assertSame(DonationReceipt::STATUS_COMPLETED, $receipt->fresh()->status);
        Mail::assertSent(DonationReceiptMail::class, 1);
        Mail::assertSent(PaymentStatusMail::class, 1);
    }

    #[Test]
    public function legacy_toyyibpay_and_fpx_records_remain_explicit_and_unchanged(): void
    {
        $toyyibpay = $this->legacyReceipt('toyyibpay', 'legacy-toyyibpay-bill');
        $fpx = $this->legacyReceipt('fpx', 'legacy-fpx-bill');

        foreach ([$toyyibpay, $fpx] as $receipt) {
            $receipt->refresh();
            $this->assertNull($receipt->payment_gateway);
            $this->assertNull($receipt->gateway_bill_id);
            $this->assertNull($receipt->gateway_collection_id);
            $this->assertTrue($receipt->usesToyyibPay());
            $this->assertFalse($receipt->isManualPayment());
        }

        $this->assertSame('toyyibpay', $toyyibpay->payment_method);
        $this->assertSame('fpx', $fpx->payment_method);
        $this->assertSame('legacy-toyyibpay-bill', $toyyibpay->toyyibpay_bill_code);
        $this->assertSame('legacy-fpx-bill', $fpx->toyyibpay_bill_code);
    }

    #[Test]
    public function bank_qr_and_cash_remain_manual_while_billplz_is_not_manual(): void
    {
        foreach (['bank_transfer', 'qr_pay', 'cash'] as $method) {
            $this->assertTrue($this->legacyReceipt($method)->isManualPayment());
        }

        $this->assertFalse($this->billplzReceipt($this->campaign())->isManualPayment());
    }

    private function campaign(string $collectedAmount = '0.00'): Campaign
    {
        return Campaign::query()->create([
            'title' => 'Kempen Billplz',
            'slug' => 'kempen-billplz-'.strtolower(str()->random(6)),
            'target_amount' => 5000,
            'collected_amount' => $collectedAmount,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function donationPayload(Campaign $campaign, string $amount = '50.00'): array
    {
        return [
            'campaign_id' => $campaign->slug,
            'donor_name' => 'Penderma Billplz',
            'donor_email' => 'donor@example.test',
            'donor_phone' => '019-123 4567',
            'amount' => $amount,
            'payment_method' => DonationReceipt::GATEWAY_BILLPLZ,
            'description' => 'Sumbangan ujian Billplz',
            'submission_token' => str()->random(64),
        ];
    }

    private function billplzReceipt(Campaign $campaign): DonationReceipt
    {
        return DonationReceipt::query()->create([
            'campaign_id' => $campaign->id,
            'receipt_no' => 'REC-BILLPLZ-'.strtoupper(str()->random(6)),
            'donor_name' => 'Penderma Billplz',
            'donor_email' => 'donor@example.test',
            'donor_phone' => '60191234567',
            'amount' => '50.00',
            'payment_method' => DonationReceipt::GATEWAY_BILLPLZ,
            'payment_gateway' => DonationReceipt::GATEWAY_BILLPLZ,
            'gateway_bill_id' => self::BILL_ID,
            'gateway_collection_id' => self::COLLECTION_ID,
            'status' => DonationReceipt::STATUS_PENDING,
            'description' => 'Sumbangan ujian Billplz',
        ]);
    }

    private function legacyReceipt(string $method, ?string $billCode = null): DonationReceipt
    {
        return DonationReceipt::query()->create([
            'receipt_no' => 'REC-LEGACY-'.strtoupper(str()->random(8)),
            'donor_name' => 'Penderma Legacy',
            'donor_email' => 'legacy@example.test',
            'donor_phone' => '0191234567',
            'amount' => '5.00',
            'payment_method' => $method,
            'toyyibpay_bill_code' => $billCode,
            'status' => DonationReceipt::STATUS_PENDING,
            'description' => 'Sumbangan legacy',
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function callbackPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'id' => self::BILL_ID,
            'collection_id' => self::COLLECTION_ID,
            'paid' => 'true',
            'state' => 'paid',
            'amount' => '5000',
            'paid_amount' => '5000',
            'due_at' => '2026-10-28',
            'email' => 'donor@example.test',
            'mobile' => '60191234567',
            'name' => 'PENDERMA BILLPLZ',
            'url' => 'https://www.billplz-sandbox.com/bills/'.self::BILL_ID,
            'paid_at' => '2026-10-28 12:30:00 +0800',
            'transaction_id' => 'TXN-12345',
            'transaction_status' => 'completed',
        ], $overrides);

        $payload['x_signature'] = $this->signature($payload);

        return $payload;
    }

    private function signedRedirectUrl(string $paid): string
    {
        $query = [
            'billplz' => [
                'id' => self::BILL_ID,
                'paid' => $paid,
                'paid_at' => '2026-10-28 12:30:00 +0800',
                'transaction_id' => 'TXN-12345',
                'transaction_status' => $paid === 'true' ? 'completed' : 'failed',
            ],
        ];
        $query['billplz']['x_signature'] = $this->signature($query);

        return route('billplz.redirect').'?'.http_build_query($query);
    }

    /**
     * Independent test implementation of the documented Billplz algorithm.
     *
     * @param  array<string, mixed>  $payload
     */
    private function signature(array $payload): string
    {
        $parts = [];
        $flatten = function (array $values, string $prefix = '') use (&$flatten, &$parts): void {
            foreach ($values as $key => $value) {
                if ((string) $key === 'x_signature') {
                    continue;
                }

                $sourceKey = $prefix.(string) $key;
                if (is_array($value)) {
                    $flatten($value, $sourceKey);
                } else {
                    $parts[] = $sourceKey.(string) $value;
                }
            }
        };
        $flatten($payload);
        usort($parts, 'strcasecmp');

        return hash_hmac('sha256', implode('|', $parts), self::SIGNATURE_KEY);
    }
}
