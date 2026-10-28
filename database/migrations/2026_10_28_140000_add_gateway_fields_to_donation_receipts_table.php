<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->string('payment_gateway', 32)->nullable()->after('payment_method');
            $table->string('gateway_bill_id', 100)->nullable()->after('toyyibpay_bill_code');
            $table->string('gateway_collection_id', 100)->nullable()->after('gateway_bill_id');
            $table->unique(
                ['payment_gateway', 'gateway_bill_id'],
                'donation_receipts_gateway_bill_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->dropUnique('donation_receipts_gateway_bill_unique');
            $table->dropColumn([
                'payment_gateway',
                'gateway_bill_id',
                'gateway_collection_id',
            ]);
        });
    }
};
