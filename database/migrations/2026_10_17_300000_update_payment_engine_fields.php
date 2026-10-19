<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty. This migration duplicated every column added by
        // 2026_10_17_200000_add_payment_verification_to_donation_receipts.
    }

    public function down(): void
    {
        // Intentionally empty. This duplicate migration owns no schema fields.
    }
};
