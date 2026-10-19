<?php

namespace Tests\Feature;

use App\Mail\PaymentStatusMail;
use App\Models\DonationReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DonationDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 10:30:00');
        config(['mail.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_create_preserves_real_donation_date_separately_from_created_at(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('receipts.store'), [
            'donor_name' => 'Hamba Allah',
            'donor_email' => 'hamba@example.test',
            'donor_phone' => '0191112222',
            'amount' => 100,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'BANK-16072026',
            'donated_at' => '2026-07-16',
            'description' => 'Sumbangan kebajikan',
        ])->assertRedirect(route('receipts.index'));

        $receipt = DonationReceipt::where('donor_name', 'Hamba Allah')->firstOrFail();
        $this->assertSame('2026-07-16', $receipt->donated_at->toDateString());
        $this->assertSame('2026-10-17', $receipt->created_at->toDateString());
        $this->assertSame('16 Julai 2026', $receipt->donation_date_label);
    }

    public function test_admin_edit_updates_donated_at_without_rewriting_created_at(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt();
        $createdAt = $receipt->created_at->copy();

        Carbon::setTestNow('2026-10-19 09:00:00');
        $this->actingAs($admin)->put(route('receipts.update', $receipt), [
            'donor_name' => $receipt->donor_name,
            'donor_email' => $receipt->donor_email,
            'donor_phone' => $receipt->donor_phone,
            'amount' => $receipt->amount,
            'payment_reference' => $receipt->payment_reference,
            'description' => $receipt->description,
            'donated_at' => '2026-07-15',
        ])->assertRedirect(route('receipts.index'));

        $receipt->refresh();
        $this->assertSame('2026-07-15', $receipt->donated_at->toDateString());
        $this->assertTrue($createdAt->equalTo($receipt->created_at));
    }

    public function test_list_detail_pdf_and_email_use_donated_at_not_created_at(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt();

        foreach ([
            $this->actingAs($admin)->get(route('receipts.index'))->getContent(),
            $this->actingAs($admin)->get(route('receipts.show', $receipt))->getContent(),
            view('pdf.donation-receipt', compact('receipt'))->render(),
            view('emails.receipt', compact('receipt'))->render(),
            view('emails.payment-status', ['receipt' => $receipt, 'event' => PaymentStatusMail::MANUAL_RECEIVED, 'audience' => 'admin'])->render(),
        ] as $rendered) {
            $this->assertStringContainsString('16 Julai 2026', $rendered);
            $this->assertStringNotContainsString('17 Oktober 2026', $rendered);
        }
    }

    public function test_legacy_null_date_is_explicit_and_never_falls_back_to_created_at(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt(['donated_at' => null]);

        $this->assertSame('Tarikh sumbangan belum ditetapkan', $receipt->donation_date_label);
        $this->actingAs($admin)->get(route('receipts.show', $receipt))
            ->assertOk()
            ->assertSeeText('Tarikh sumbangan belum ditetapkan');
        $this->assertStringContainsString(
            'Tarikh sumbangan belum ditetapkan',
            view('pdf.donation-receipt', compact('receipt'))->render(),
        );
    }

    private function receipt(array $overrides = []): DonationReceipt
    {
        return DonationReceipt::create(array_merge([
            'receipt_no' => 'REC-DATE-'.strtoupper(substr(uniqid(), -6)),
            'donor_name' => 'Hamba Allah',
            'donor_email' => 'hamba@example.test',
            'donor_phone' => '0191112222',
            'amount' => 100,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'BANK-16072026',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => '2026-07-16',
            'description' => 'Sumbangan',
            'created_at' => '2026-10-17 10:30:00',
            'updated_at' => '2026-10-17 10:30:00',
        ], $overrides));
    }
}
