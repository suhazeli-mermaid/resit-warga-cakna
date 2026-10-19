<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $hasLegacyColumns = $connection->withoutPretending(
            fn (): bool => $connection->getSchemaBuilder()->hasColumns(
                'donation_receipts',
                ['reference_no', 'payment_reference'],
            ),
        );

        if (! $hasLegacyColumns) {
            return;
        }

        [$conflictCount, $conflictIds] = $connection->withoutPretending(function (): array {
            $conflicts = DB::table('donation_receipts')
                ->whereNotNull('reference_no')
                ->where('reference_no', '<>', '')
                ->whereNotNull('payment_reference')
                ->where('payment_reference', '<>', '')
                ->whereColumn('reference_no', '<>', 'payment_reference');

            return [
                (clone $conflicts)->count(),
                (clone $conflicts)->orderBy('id')->limit(100)->pluck('id')->all(),
            ];
        });

        if ($conflictCount > 0) {
            Log::warning('Legacy payment reference reconciliation found conflicting values.', [
                'conflict_count' => $conflictCount,
                'receipt_ids' => $conflictIds,
                'receipt_ids_truncated' => $conflictCount > count($conflictIds),
            ]);
        }

        DB::table('donation_receipts')
            ->where(function ($query) {
                $query->whereNull('payment_reference')
                    ->orWhere('payment_reference', '');
            })
            ->whereNotNull('reference_no')
            ->where('reference_no', '<>', '')
            ->update(['payment_reference' => DB::raw('reference_no')]);
    }

    public function down(): void
    {
        // Intentionally non-destructive: copied canonical references and the
        // legacy source column must both survive a rollback.
    }
};
