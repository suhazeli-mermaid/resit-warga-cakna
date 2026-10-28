<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            // New writes use the encrypted identity_document field. The legacy
            // ic_passport column remains available for a controlled backfill.
            $table->text('identity_document')->nullable();
            $table->text('address')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->text('bank_account_no')->nullable();

            $table->boolean('is_donor_favourite')->default(true);
            $table->boolean('is_voucher_recipient_favourite')->default(false);
            $table->boolean('is_active')->default(true);

            // Keyed hashes support duplicate lookup without indexing full
            // personal identity, phone or email values.
            $table->char('email_lookup_hash', 64)->nullable()->index();
            $table->char('phone_lookup_hash', 64)->nullable()->index();
            $table->char('identity_lookup_hash', 64)->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable()->index();

            $table->index(['is_active', 'is_donor_favourite']);
            $table->index(['is_active', 'is_voucher_recipient_favourite']);
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'identity_document',
                'address',
                'bank_name',
                'bank_account_no',
                'is_donor_favourite',
                'is_voucher_recipient_favourite',
                'is_active',
                'email_lookup_hash',
                'phone_lookup_hash',
                'identity_lookup_hash',
                'created_by',
                'updated_by',
                'last_used_at',
            ]);
        });
    }
};
