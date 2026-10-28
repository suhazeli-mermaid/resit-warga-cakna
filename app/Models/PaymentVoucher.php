<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const ISSUED = 'issued';

    public const PAYMENT_CONFIRMED = 'payment_confirmed';

    public const AWAITING_ACKNOWLEDGEMENT = 'awaiting_acknowledgement';

    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const ARCHIVED = 'archived';

    protected $fillable = [
        'voucher_no', 'voucher_date', 'status', 'applicant_id', 'approver_id',
        'payment_confirmed_by', 'reissued_from_id', 'recipient_name',
        'recipient_phone', 'recipient_email', 'recipient_address', 'recipient_ic',
        'bank_name', 'bank_account_no', 'purpose', 'amount',
        'committee_approval_date', 'submitted_at', 'approved_at', 'issued_at',
        'paid_amount', 'transaction_date', 'transaction_reference',
        'payment_proof_path', 'payment_confirmed_at', 'completed_at',
        'cancelled_at', 'archived_at', 'applicant_signature_snapshot_path',
        'applicant_signature_snapshot_checksum', 'approver_signature_snapshot_path',
        'approver_signature_snapshot_checksum', 'stamp_snapshot_path',
        'stamp_snapshot_checksum', 'document_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'committee_approval_date' => 'date',
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'recipient_ic' => 'encrypted',
            'bank_account_no' => 'encrypted',
            'transaction_reference' => 'encrypted',
            'document_snapshot' => 'array',
        ];
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function paymentConfirmer()
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function audits()
    {
        return $this->hasMany(PaymentVoucherAudit::class);
    }

    public function acknowledgements()
    {
        return $this->hasMany(VoucherAcknowledgement::class);
    }

    public function latestAcknowledgement()
    {
        return $this->hasOne(VoucherAcknowledgement::class)->latestOfMany();
    }

    public function latestAcknowledgementSentAudit()
    {
        return $this->hasOne(PaymentVoucherAudit::class)
            ->ofMany(['created_at' => 'max', 'id' => 'max'], function (Builder $query) {
                $query->where('event', 'acknowledgement_sent');
            });
    }

    public function reissuedFrom()
    {
        return $this->belongsTo(self::class, 'reissued_from_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::DRAFT => 'DRAF',
            self::PENDING_APPROVAL => 'MENUNGGU KELULUSAN',
            self::APPROVED => 'DILULUSKAN',
            self::ISSUED => 'BAUCAR DIKELUARKAN',
            self::PAYMENT_CONFIRMED => 'BAYARAN DISAHKAN',
            self::AWAITING_ACKNOWLEDGEMENT => 'MENUNGGU AKUAN PENERIMA',
            self::COMPLETED => 'SELESAI',
            self::REJECTED => 'DITOLAK',
            self::CANCELLED => 'DIBATALKAN',
            self::ARCHIVED => 'DIARKIBKAN',
            default => strtoupper($this->status),
        };
    }

    public function isIssuedDocument(): bool
    {
        return $this->issued_at !== null;
    }

    /**
     * Resolve signatory identity from the immutable document snapshot.
     * Live profile data is only used before a document has been issued.
     */
    public function documentSignatoryIdentity(string $kind): array
    {
        if (! in_array($kind, ['applicant', 'approver'], true)) {
            throw new \InvalidArgumentException('Jenis penandatangan tidak sah.');
        }

        $snapshot = $this->document_snapshot;
        $hasSnapshot = is_array($snapshot) && $snapshot !== [];
        $user = $kind === 'applicant' ? $this->applicant : $this->approver;

        if ($hasSnapshot) {
            $fullName = $snapshot[$kind.'_full_name'] ?? $snapshot[$kind.'_name'] ?? '';
            $salutation = $snapshot[$kind.'_salutation'] ?? null;
            $position = $snapshot[$kind.'_organization_position'] ?? null;
        } else {
            $fullName = $user?->name ?? '';
            $salutation = $user?->salutation;
            $position = $user?->organization_position;
        }

        return [
            'salutation' => $salutation,
            'full_name' => $fullName,
            'display_name' => User::formatSignatoryName($fullName, $salutation),
            'organization_position' => $position,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isSecretary()) {
            return $query;
        }

        if ($user->isTreasurer()) {
            return $query->whereIn('status', [
                self::APPROVED,
                self::ISSUED,
                self::PAYMENT_CONFIRMED,
                self::AWAITING_ACKNOWLEDGEMENT,
                self::COMPLETED,
                self::ARCHIVED,
            ]);
        }

        return $query->where('applicant_id', $user->id);
    }
}
