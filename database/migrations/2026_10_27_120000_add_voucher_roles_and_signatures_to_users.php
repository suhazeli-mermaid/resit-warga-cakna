<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('digital_signature_path', 255)->nullable()->after('role');
            $table->char('digital_signature_checksum', 64)->nullable()->after('digital_signature_path');
            $table->timestamp('digital_signature_uploaded_at')->nullable()->after('digital_signature_checksum');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'digital_signature_path',
                'digital_signature_checksum',
                'digital_signature_uploaded_at',
            ]);
        });
    }
};
