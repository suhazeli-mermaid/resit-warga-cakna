<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_voucher_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_voucher_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_no', 32);
            $table->string('event', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 30)->default('user');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_voucher_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        Schema::create('voucher_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_voucher_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('recipient_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('recipient_signature_path', 255)->nullable();
            $table->timestamps();

            $table->index(['payment_voucher_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_acknowledgements');
        Schema::dropIfExists('payment_voucher_audits');
    }
};
