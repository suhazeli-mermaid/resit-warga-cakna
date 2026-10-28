<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const SALUTATIONS = [
        'Dr',
        'Tuan',
        'Puan',
        'Encik',
        'Cik',
        'Haji',
        'Hajah',
        "Dato'",
        'Datin',
        'Tiada',
    ];

    protected $fillable = [
        'name',
        'salutation',
        'email',
        'password',
        'role',
        'organization_position',
        'digital_signature_path',
        'digital_signature_checksum',
        'digital_signature_uploaded_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'digital_signature_uploaded_at' => 'datetime',
        ];
    }

    // Semakan peranan automatik
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAjk(): bool
    {
        return $this->isAdmin() || $this->role === 'ajk';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'setiausaha';
    }

    public function isTreasurer(): bool
    {
        return $this->role === 'bendahari';
    }

    public function hasVoucherRole(): bool
    {
        return in_array($this->role, ['admin', 'setiausaha', 'bendahari', 'ajk'], true);
    }

    public function paymentVouchers()
    {
        return $this->hasMany(PaymentVoucher::class, 'applicant_id');
    }

    public function hasDigitalSignature(): bool
    {
        return filled($this->digital_signature_path)
            && Storage::disk('local')->exists($this->digital_signature_path);
    }

    public static function isValidIndividualName(?string $name): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $name)));

        if (mb_strlen($normalized) < 2 || ! preg_match('/\p{L}/u', $normalized)) {
            return false;
        }

        return ! in_array($normalized, [
            'warga cakna',
            'persatuan prihatin warga terpinggir terengganu',
            'persatuan prihatin warga terpinggir terengganu (warga cakna)',
            'admin',
            'administrator',
        ], true);
    }

    public function hasValidIndividualName(): bool
    {
        return self::isValidIndividualName($this->name);
    }

    public function hasOrganizationPosition(): bool
    {
        return filled(trim((string) $this->organization_position));
    }

    public function hasCompleteVoucherIdentity(): bool
    {
        return $this->hasValidIndividualName() && $this->hasOrganizationPosition();
    }

    public static function formatSignatoryName(?string $fullName, ?string $salutation): string
    {
        $fullName = trim((string) $fullName);

        if (! filled($salutation) || $salutation === 'Tiada') {
            return $fullName;
        }

        return trim($salutation.' '.$fullName);
    }

    public function signatoryName(): string
    {
        return self::formatSignatoryName($this->name, $this->salutation);
    }

    /**
     * Immutable identity values copied into an issued voucher document snapshot.
     * Access-control roles are intentionally not used as document positions.
     */
    public function voucherIdentitySnapshot(): array
    {
        return [
            'salutation' => $this->salutation,
            'full_name' => trim((string) $this->name),
            'organization_position' => trim((string) $this->organization_position),
        ];
    }
}
