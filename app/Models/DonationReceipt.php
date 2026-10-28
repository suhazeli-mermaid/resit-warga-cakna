<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DonationReceipt extends Model
{
    use HasFactory, SoftDeletes;

    public const GATEWAY_BILLPLZ = 'billplz';

    public const GATEWAY_TOYYIBPAY = 'toyyibpay';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'campaign_id',
        'receipt_no',
        'donor_name',
        'donor_ic_passport',
        'donor_phone',
        'donor_email',
        'amount',
        'payment_method',
        'payment_gateway',
        'payment_reference',
        'toyyibpay_bill_code',
        'gateway_bill_id',
        'gateway_collection_id',
        'proof_file',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'archived_at',
        'donated_at',
        'description',
    ];

    protected $casts = [
        'donated_at' => 'date',
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Hubungan ke User yang cipta resit
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function audits()
    {
        return $this->hasMany(DonationReceiptAudit::class);
    }

    public function latestAudit()
    {
        return $this->hasOne(DonationReceiptAudit::class)->latestOfMany();
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isManualPayment(): bool
    {
        return ! $this->usesToyyibPay() && ! $this->usesBillplz();
    }

    public function usesToyyibPay(): bool
    {
        return $this->payment_gateway === self::GATEWAY_TOYYIBPAY
            || $this->payment_method === self::GATEWAY_TOYYIBPAY
            || filled($this->toyyibpay_bill_code);
    }

    public function usesBillplz(): bool
    {
        return $this->payment_gateway === self::GATEWAY_BILLPLZ
            || $this->payment_method === self::GATEWAY_BILLPLZ
            || filled($this->gateway_bill_id);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'BERJAYA',
            self::STATUS_PENDING => $this->usesBillplz()
                ? 'MENUNGGU PEMBAYARAN'
                : 'TUNGGU PENGESAHAN',
            self::STATUS_REJECTED => 'DITOLAK',
            self::STATUS_FAILED => 'GAGAL',
            default => strtoupper((string) $this->status),
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'border-green-300 bg-green-100 text-green-800',
            self::STATUS_PENDING => 'border-amber-300 bg-amber-100 text-amber-800',
            self::STATUS_REJECTED => 'border-red-300 bg-red-100 text-red-800',
            self::STATUS_FAILED => 'border-gray-400 bg-gray-100 text-gray-800',
            default => 'border-gray-300 bg-gray-100 text-gray-700',
        };
    }

    public function getDonationDateLabelAttribute(): string
    {
        if ($this->donated_at === null) {
            return 'Tarikh sumbangan belum ditetapkan';
        }

        return $this->donated_at->locale('ms')->translatedFormat('j F Y');
    }
}
