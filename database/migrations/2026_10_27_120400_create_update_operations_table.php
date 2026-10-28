<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->index();
            $table->char('current_commit', 40)->nullable();
            $table->char('target_commit', 40)->nullable();
            $table->string('target_message', 500)->nullable();
            $table->unsignedInteger('changed_files_count')->default(0);
            $table->json('details')->nullable();
            $table->string('code_backup_path', 500)->nullable();
            $table->char('code_backup_checksum', 64)->nullable();
            $table->string('database_backup_path', 500)->nullable();
            $table->char('database_backup_checksum', 64)->nullable();
            $table->timestamp('preflight_passed_at')->nullable();
            $table->timestamp('backup_verified_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_operations');
    }
};
