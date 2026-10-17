<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'receipt_no',
        'donor_name',
        'donor_ic_passport',
        'donor_phone',
        'donor_email',
        'amount',
        'payment_method',
        'payment_reference',
        'donated_at',
        'description',
    ];

    protected $casts = [
        'donated_at' => 'date',
        'amount' => 'decimal:2',
    ];

    // Hubungan ke User yang cipta resit
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}