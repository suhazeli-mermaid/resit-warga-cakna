<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_number_sequences', function (Blueprint $table) {
            $table->date('voucher_date')->primary();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_number_sequences');
    }
};
