
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->unique()
                ->constrained('campaigns')
                ->cascadeOnDelete();

            $table->longText('background')->nullable();
            $table->longText('objectives')->nullable();
            $table->longText('beneficiaries')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('organiser')->nullable();
            $table->string('contact_info')->nullable();

            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('campaign_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            $table->date('activity_date');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['campaign_id', 'activity_date']);
        });

        Schema::create('campaign_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            $table->foreignId('campaign_update_id')
                ->nullable()
                ->constrained('campaign_updates')
                ->cascadeOnDelete();

            $table->enum('media_type', ['image', 'youtube']);
            $table->string('file_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index([
                'campaign_id',
                'sort_order'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_media');
        Schema::dropIfExists('campaign_updates');
        Schema::dropIfExists('campaign_details');
    }
};
