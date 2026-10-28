<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('mail_type', 100);
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_email', 254);
            $table->string('subject', 500);
            $table->string('related_type', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_reference', 150)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->foreignId('resend_of_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->foreignId('resent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['mail_type', 'created_at']);
            $table->index(['related_type', 'related_id']);
            $table->index(['related_reference', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
