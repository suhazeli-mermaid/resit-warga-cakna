<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'target_amount',
        'collected_amount',
        'image_path',
        'is_active',
    ];

    public function receipts()
    {
        return $this->hasMany(DonationReceipt::class);
    }
}