<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_approve_voucher')->default(false)->after('can_create_voucher');
            $table->boolean('can_confirm_payment')->default(false)->after('can_approve_voucher');
            $table->boolean('can_manage_favourites')->default(false)->after('can_confirm_payment');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_approve_voucher', 'can_confirm_payment', 'can_manage_favourites']);
        });
    }
};
