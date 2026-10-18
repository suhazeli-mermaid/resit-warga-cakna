<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDetail extends Model
{
    protected $fillable = [
        'campaign_id',
        'background',
        'objectives',
        'beneficiaries',
        'location',
        'start_date',
        'end_date',
        'organiser',
        'contact_info',
        'is_published',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_published' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
