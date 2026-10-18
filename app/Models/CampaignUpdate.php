<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignUpdate extends Model
{
    protected $fillable = [
        'campaign_id',
        'activity_date',
        'title',
        'description',
        'location',
        'is_published',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'is_published' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function media()
    {
        return $this->hasMany(
            CampaignMedia::class,
            'campaign_update_id'
        )->orderBy('sort_order');
    }
}
