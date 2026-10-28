<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 32)->unique();
            $table->date('voucher_date')->index();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reissued_from_id')->nullable()->constrained('payment_vouchers')->nullOnDelete();
            $table->string('recipient_name', 255);
            $table->string('recipient_phone', 50);
            $table->string('recipient_email', 255);
            $table->text('recipient_address')->nullable();
            $table->text('recipient_ic')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->text('bank_account_no')->nullable();
            $table->text('purpose');
            $table->decimal('amount', 13, 2);
            $table->date('committee_approval_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->decimal('paid_amount', 13, 2)->nullable();
            $table->date('transaction_date')->nullable();
            $table->text('transaction_reference')->nullable();
            $table->string('payment_proof_path', 255)->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('applicant_signature_snapshot_path', 255)->nullable();
            $table->char('applicant_signature_snapshot_checksum', 64)->nullable();
            $table->string('approver_signature_snapshot_path', 255)->nullable();
            $table->char('approver_signature_snapshot_checksum', 64)->nullable();
            $table->string('stamp_snapshot_path', 255)->nullable();
            $table->char('stamp_snapshot_checksum', 64)->nullable();
            $table->json('document_snapshot')->nullable();
            $table->timestamps();

            $table->index(['applicant_id', 'status']);
            $table->index(['status', 'voucher_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};
