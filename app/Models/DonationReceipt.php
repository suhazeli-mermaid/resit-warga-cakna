<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_no',
        'donor_name',
        'donor_ic_passport',
        'donor_phone',
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
}