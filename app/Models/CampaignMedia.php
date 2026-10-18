<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignMedia extends Model
{
    protected $fillable = [
        'campaign_id',
        'campaign_update_id',
        'media_type',
        'file_path',
        'youtube_url',
        'caption',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function activity()
{
    return $this->belongsTo(
        CampaignUpdate::class,
        'campaign_update_id'
    );
}

    public function getImageUrlAttribute(): ?string
    {
        if (
            $this->media_type !== 'image' ||
            !$this->file_path
        ) {
            return null;
        }

        return asset('storage/' . $this->file_path);
    }
}
