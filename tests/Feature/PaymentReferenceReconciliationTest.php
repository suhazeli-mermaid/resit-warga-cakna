<?php

namespace Tests\Feature;

use App\Models\DonationReceipt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PaymentReferenceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_verification_migrations_are_idempotent_and_non_destructive(): void
    {
        $verificationColumns = [
            'proof_file',
            'verified_by',
            'verified_at',
            'rejection_reason',
        ];

        foreach ($verificationColumns as $column) {
            $this->assertTrue(Schema::hasColumn('donation_receipts', $column));
        }
        $this->assertFalse(Schema::hasColumn('donation_receipts', 'reference_no'));

        $paymentVerification = require database_path('migrations/2026_10_17_200000_add_payment_verification_to_donation_receipts.php');
        $paymentVerification->up();
        $paymentVerification->down();

        foreach ($verificationColumns as $column) {
            $this->assertTrue(Schema::hasColumn('donation_receipts', $column));
        }
        $this->assertFalse(Schema::hasColumn('donation_receipts', 'reference_no'));

        $columnsBeforeNoOp = Schema::getColumnListing('donation_receipts');
        sort($columnsBeforeNoOp);

        $duplicateMigration = require database_path('migrations/2026_10_17_300000_update_payment_engine_fields.php');
        $duplicateMigration->up();
        $duplicateMigration->down();

        $columnsAfterNoOp = Schema::getColumnListing('donation_receipts');
        sort($columnsAfterNoOp);
        $this->assertSame($columnsBeforeNoOp, $columnsAfterNoOp);
    }

    public function test_reconciliation_copies_only_empty_canonical_references_and_reports_conflicts(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->string('reference_no')->nullable();
        });

        $copyCandidate = $this->makeReceipt(null);
        $conflict = $this->makeReceipt('canonical-ref');

        DB::table('donation_receipts')->where('id', $copyCandidate->id)->update([
            'reference_no' => 'legacy-ref',
        ]);
        DB::table('donation_receipts')->where('id', $conflict->id)->update([
            'reference_no' => 'different-legacy-ref',
        ]);

        Log::spy();

        $migration = require database_path('migrations/2026_10_19_000000_reconcile_legacy_payment_references.php');
        $migration->up();

        $this->assertSame('legacy-ref', $copyCandidate->fresh()->payment_reference);
        $this->assertSame('canonical-ref', $conflict->fresh()->payment_reference);
        $this->assertTrue(Schema::hasColumn('donation_receipts', 'reference_no'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Legacy payment reference reconciliation found conflicting values.',
                Mockery::on(fn (array $context): bool => $context['conflict_count'] === 1
                    && $context['receipt_ids'] === [$conflict->id]
                    && $context['receipt_ids_truncated'] === false),
            );

        $migration->down();
        $this->assertTrue(Schema::hasColumn('donation_receipts', 'reference_no'));
        $this->assertSame('legacy-ref', $copyCandidate->fresh()->payment_reference);
    }

    private function makeReceipt(?string $paymentReference): DonationReceipt
    {
        return DonationReceipt::query()->create([
            'receipt_no' => 'REC-RECON-'.strtoupper(substr(uniqid(), -6)),
            'donor_name' => 'Penderma Rekonsiliasi',
            'donor_email' => 'reconciliation@example.test',
            'donor_phone' => '0191234567',
            'amount' => 50,
            'payment_method' => 'bank_transfer',
            'payment_reference' => $paymentReference,
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => now(),
            'description' => 'Ujian rekonsiliasi',
        ]);
    }
}
