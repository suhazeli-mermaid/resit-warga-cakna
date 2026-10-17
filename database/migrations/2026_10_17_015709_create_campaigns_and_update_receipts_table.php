<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cipta Jadual Campaigns
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('target_amount', 12, 2)->default(0);
            $table->decimal('collected_amount', 12, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Kemaskini Jadual donation_receipts untuk sokong Kempen & Status Bayaran
        Schema::table('donation_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('donation_receipts', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null')->after('user_id');
            }
            if (!Schema::hasColumn('donation_receipts', 'status')) {
                $table->string('status')->default('pending')->after('payment_method');
            }
            if (!Schema::hasColumn('donation_receipts', 'toyyibpay_bill_code')) {
                $table->string('toyyibpay_bill_code')->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['campaign_id', 'status', 'toyyibpay_bill_code']);
        });

        Schema::dropIfExists('campaigns');
    }
};