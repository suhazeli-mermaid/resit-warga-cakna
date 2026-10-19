<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationReceiptAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'donation_receipt_id',
        'receipt_no',
        'action',
        'actor_id',
        'actor_type',
        'reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->belongsTo(DonationReceipt::class, 'donation_receipt_id')->withTrashed();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
