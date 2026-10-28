<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucherAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_voucher_id', 'voucher_no', 'event', 'actor_id', 'actor_type',
        'reason', 'metadata', 'created_at',
    ];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function voucher()
    {
        return $this->belongsTo(PaymentVoucher::class, 'payment_voucher_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
