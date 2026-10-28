<?php

namespace Tests\Feature;

use App\Models\DonationReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceiptBillplzListingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function billplz_pending_is_visible_near_the_top_with_gateway_aware_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        for ($index = 1; $index <= 15; $index++) {
            $this->manualReceipt([
                'receipt_no' => 'REC-OLDER-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'donated_at' => '2026-07-'.str_pad((string) min($index, 28), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $billplz = $this->billplzReceipt();

        $response = $this->actingAs($admin)->get(route('receipts.index'));

        $response->assertOk()
            ->assertSeeText($billplz->receipt_no)
            ->assertSeeText('MENUNGGU PEMBAYARAN');
        $this->assertSame($billplz->receipt_no, $response->viewData('receipts')->first()->receipt_no);
    }

    #[Test]
    public function billplz_pending_has_no_manual_verify_or_reject_action_and_backend_blocks_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $billplz = $this->billplzReceipt();

        $html = $this->actingAs($admin)->get(route('receipts.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('receipts.verify', $billplz), $html);
        $this->assertStringNotContainsString(route('receipts.reject', $billplz), $html);
        $this->assertStringContainsString('Kemaskini', $html);

        $this->actingAs($admin)->patch(route('receipts.verify', $billplz))->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('receipts.reject', $billplz), [
            'rejection_reason' => 'Tidak patut digunakan untuk gateway.',
        ])->assertSessionHas('error');

        $this->assertSame(DonationReceipt::STATUS_PENDING, $billplz->fresh()->status);
    }

    #[Test]
    public function completed_billplz_and_pending_manual_have_semantically_correct_labels_everywhere(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $billplzPending = $this->billplzReceipt(['receipt_no' => 'REC-BILLPLZ-PENDING']);
        $billplzCompleted = $this->billplzReceipt([
            'receipt_no' => 'REC-BILLPLZ-COMPLETED',
            'gateway_bill_id' => 'bill-completed',
            'status' => DonationReceipt::STATUS_COMPLETED,
            'donated_at' => '2026-10-28',
        ]);
        $manualPending = $this->manualReceipt(['receipt_no' => 'REC-MANUAL-PENDING']);

        $this->assertSame('MENUNGGU PEMBAYARAN', $billplzPending->status_label);
        $this->assertSame('BERJAYA', $billplzCompleted->status_label);
        $this->assertSame('TUNGGU PENGESAHAN', $manualPending->status_label);

        $this->actingAs($admin)->get(route('receipts.index'))
            ->assertOk()
            ->assertSeeText('MENUNGGU PEMBAYARAN')
            ->assertSeeText('BERJAYA')
            ->assertSeeText('TUNGGU PENGESAHAN');
        $this->actingAs($admin)->get(route('receipts.show', $billplzPending))
            ->assertOk()
            ->assertSeeText('MENUNGGU PEMBAYARAN');
        $this->assertStringContainsString(
            'MENUNGGU PEMBAYARAN',
            view('pdf.donation-receipt', ['receipt' => $billplzPending])->render(),
        );
        $this->assertStringContainsString(
            'BERJAYA',
            view('emails.receipt', ['receipt' => $billplzCompleted])->render(),
        );
    }

    #[Test]
    public function filters_search_and_counts_include_billplz_receipts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $billplzPending = $this->billplzReceipt(['receipt_no' => 'REC-BILLPLZ-SEARCH']);
        $manualPending = $this->manualReceipt(['receipt_no' => 'REC-MANUAL-FILTER']);
        $billplzCompleted = $this->billplzReceipt([
            'receipt_no' => 'REC-BILLPLZ-DONE',
            'gateway_bill_id' => 'bill-done',
            'status' => DonationReceipt::STATUS_COMPLETED,
            'donated_at' => '2026-10-28',
        ]);
        $billplzFailed = $this->billplzReceipt([
            'receipt_no' => 'REC-BILLPLZ-FAILED',
            'gateway_bill_id' => 'bill-failed',
            'status' => DonationReceipt::STATUS_FAILED,
        ]);

        $this->actingAs($admin)->get(route('receipts.index'))
            ->assertOk()
            ->assertSeeText('Semua (4)')
            ->assertSeeText('Menunggu (2)')
            ->assertSeeText('Berjaya (1)')
            ->assertSeeText('Gagal (1)')
            ->assertSeeText($billplzFailed->receipt_no);

        $this->actingAs($admin)->get(route('receipts.index', ['filter' => 'pending']))
            ->assertOk()
            ->assertSeeText($billplzPending->receipt_no)
            ->assertSeeText($manualPending->receipt_no)
            ->assertDontSeeText($billplzCompleted->receipt_no)
            ->assertDontSeeText($billplzFailed->receipt_no);

        $this->actingAs($admin)->get(route('receipts.index', ['filter' => 'failed']))
            ->assertOk()
            ->assertSeeText($billplzFailed->receipt_no)
            ->assertDontSeeText($billplzPending->receipt_no);

        $this->actingAs($admin)->get(route('receipts.index', ['search' => 'BILLPLZ-SEARCH']))
            ->assertOk()
            ->assertSeeText($billplzPending->receipt_no)
            ->assertDontSeeText($manualPending->receipt_no)
            ->assertDontSeeText($billplzCompleted->receipt_no)
            ->assertDontSeeText($billplzFailed->receipt_no);
    }

    private function billplzReceipt(array $overrides = []): DonationReceipt
    {
        return DonationReceipt::query()->create(array_merge([
            'receipt_no' => 'REC-BILLPLZ-'.strtoupper(str()->random(6)),
            'donor_name' => 'Penderma Billplz',
            'donor_email' => 'billplz@example.test',
            'donor_phone' => '0191234567',
            'amount' => '10.00',
            'payment_method' => DonationReceipt::GATEWAY_BILLPLZ,
            'payment_gateway' => DonationReceipt::GATEWAY_BILLPLZ,
            'gateway_bill_id' => 'bill-'.strtolower(str()->random(8)),
            'gateway_collection_id' => 'collection-test',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => null,
            'description' => 'Sumbangan Billplz',
        ], $overrides));
    }

    private function manualReceipt(array $overrides = []): DonationReceipt
    {
        return DonationReceipt::query()->create(array_merge([
            'receipt_no' => 'REC-MANUAL-'.strtoupper(str()->random(6)),
            'donor_name' => 'Penderma Manual',
            'donor_email' => 'manual@example.test',
            'donor_phone' => '0197654321',
            'amount' => '20.00',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'MANUAL-REF',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => '2026-07-16',
            'description' => 'Sumbangan Manual',
        ], $overrides));
    }
}
