<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('donation_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('donation_receipts', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('donation_receipts', 'proof_file')) {
                $table->string('proof_file')->nullable()->after('reference_no');
            }
            if (!Schema::hasColumn('donation_receipts', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            }
            if (!Schema::hasColumn('donation_receipts', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('donation_receipts', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void {
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->dropColumn(['proof_file', 'verified_by', 'verified_at', 'rejection_reason']);
        });
    }
};