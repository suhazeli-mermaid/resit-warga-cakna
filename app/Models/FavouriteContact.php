<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FavouriteContact extends Model
{
    use HasFactory;

    public const USAGE_DONOR = 'donor';

    public const USAGE_VOUCHER = 'voucher';

    protected $table = 'donors';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'identity_document',
        'address',
        'bank_name',
        'bank_account_no',
        'is_donor_favourite',
        'is_voucher_recipient_favourite',
        'is_active',
        'email_lookup_hash',
        'phone_lookup_hash',
        'identity_lookup_hash',
        'created_by',
        'updated_by',
        'last_used_at',
    ];

    protected $hidden = [
        'ic_passport',
        'identity_document',
        'bank_account_no',
        'email_lookup_hash',
        'phone_lookup_hash',
        'identity_lookup_hash',
    ];

    protected function casts(): array
    {
        return [
            'identity_document' => 'encrypted',
            'bank_account_no' => 'encrypted',
            'is_donor_favourite' => 'boolean',
            'is_voucher_recipient_favourite' => 'boolean',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUsage(Builder $query, string $usage): Builder
    {
        return match ($usage) {
            self::USAGE_DONOR => $query->where('is_donor_favourite', true),
            self::USAGE_VOUCHER => $query->where('is_voucher_recipient_favourite', true),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function identityValue(): ?string
    {
        return filled($this->identity_document)
            ? $this->identity_document
            : (filled($this->getRawOriginal('ic_passport')) ? (string) $this->getRawOriginal('ic_passport') : null);
    }

    public function usageLabel(): string
    {
        return match (true) {
            $this->is_donor_favourite && $this->is_voucher_recipient_favourite => 'Kedua-duanya',
            $this->is_voucher_recipient_favourite => 'Penerima Baucar',
            default => 'Pembayar / Penderma',
        };
    }

    public function maskedIdentity(): string
    {
        return $this->mask($this->identityValue(), 4);
    }

    public function maskedBankAccount(): string
    {
        return $this->mask($this->bank_account_no, 4);
    }

    private function mask(?string $value, int $visible): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        $length = mb_strlen($value);

        return str_repeat('•', max(4, $length - $visible)).mb_substr($value, -$visible);
    }
}
