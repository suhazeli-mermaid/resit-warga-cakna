<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            // A pending ToyyibPay bill has no verified donation date yet.
            $table->date('donated_at')->nullable()->change();

            if (! Schema::hasColumn('donation_receipts', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('rejection_reason')->index();
            }

            if (! Schema::hasColumn('donation_receipts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasTable('donation_receipt_audits')) {
            Schema::create('donation_receipt_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('donation_receipt_id')->nullable()->constrained()->nullOnDelete();
                $table->string('receipt_no');
                $table->string('action', 40);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_type', 40)->default('admin');
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['donation_receipt_id', 'action']);
                $table->index(['receipt_no', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive. Receipt lifecycle and audit data are
        // financial records and must not be dropped by an accidental rollback.
    }
};
