<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('donation_receipts', 'proof_file')) {
                $table->string('proof_file')->nullable()->after('toyyibpay_bill_code');
            }
            if (! Schema::hasColumn('donation_receipts', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('donation_receipts', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (! Schema::hasColumn('donation_receipts', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive. Because up() conditionally adds only
        // missing columns, a rollback cannot know which columns it actually
        // created and must not remove fields owned by another migration/hotfix.
    }
};
