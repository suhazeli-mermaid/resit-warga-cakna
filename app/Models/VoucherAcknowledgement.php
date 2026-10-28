<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherAcknowledgement extends Model
{
    protected $fillable = [
        'payment_voucher_id', 'token_hash', 'expires_at', 'used_at',
        'recipient_name', 'notes', 'recipient_signature_path',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function voucher()
    {
        return $this->belongsTo(PaymentVoucher::class, 'payment_voucher_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function getAcknowledgementNoAttribute(): string
    {
        $voucherNo = $this->voucher?->voucher_no;

        return $voucherNo && str_starts_with($voucherNo, 'B-')
            ? 'A-'.substr($voucherNo, 2)
            : 'A-'.str_pad((string) $this->getKey(), 8, '0', STR_PAD_LEFT);
    }

    public function getStatusAttribute(): string
    {
        if ($this->used_at !== null) {
            return 'completed';
        }

        return $this->expires_at->isPast() ? 'expired' : 'pending';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'SELESAI',
            'expired' => 'TAMAT TEMPOH',
            default => 'MENUNGGU AKUAN',
        };
    }
}
