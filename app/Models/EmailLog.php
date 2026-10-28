<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailLog extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const TYPE_DONATION_RECEIPT = 'donation_receipt';

    public const TYPE_PAYMENT_MANUAL_DONOR = 'payment_manual_received_donor';

    public const TYPE_PAYMENT_MANUAL_ADMIN = 'payment_manual_received_admin';

    public const TYPE_PAYMENT_COMPLETED_ADMIN = 'payment_completed_admin';

    public const TYPE_PAYMENT_REJECTED_DONOR = 'payment_rejected_donor';

    public const TYPE_PAYMENT_REJECTED_ADMIN = 'payment_rejected_admin';

    public const TYPE_VOUCHER_ISSUED_APPLICANT = 'voucher_issued_applicant';

    public const TYPE_VOUCHER_ISSUED_ADMIN = 'voucher_issued_admin';

    public const TYPE_ACKNOWLEDGEMENT_REQUEST = 'voucher_acknowledgement_request';

    public const TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT = 'voucher_acknowledgement_completed_recipient';

    public const TYPE_ACKNOWLEDGEMENT_COMPLETED_APPLICANT = 'voucher_acknowledgement_completed_applicant';

    public const TYPE_PASSWORD_RESET = 'password_reset';

    public const TYPE_EMAIL_VERIFICATION = 'email_verification';

    public const RELATED_RECEIPT = 'donation_receipt';

    public const RELATED_VOUCHER = 'payment_voucher';

    public const RELATED_ACKNOWLEDGEMENT = 'voucher_acknowledgement';

    public const RELATED_USER = 'user';

    protected $fillable = [
        'public_id',
        'mail_type',
        'recipient_name',
        'recipient_email',
        'subject',
        'related_type',
        'related_id',
        'related_reference',
        'status',
        'attempted_at',
        'sent_at',
        'failed_at',
        'failure_message',
        'resend_of_id',
        'resent_by',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailLog $log) {
            $log->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function resendOf()
    {
        return $this->belongsTo(self::class, 'resend_of_id');
    }

    public function resends()
    {
        return $this->hasMany(self::class, 'resend_of_id');
    }

    public function resentBy()
    {
        return $this->belongsTo(User::class, 'resent_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::SENT => 'DIHANTAR',
            self::FAILED => 'GAGAL',
            default => 'MENUNGGU',
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            self::SENT => 'border-green-300 bg-green-100 text-green-800',
            self::FAILED => 'border-red-300 bg-red-100 text-red-800',
            default => 'border-amber-300 bg-amber-100 text-amber-800',
        };
    }

    public function getMailTypeLabelAttribute(): string
    {
        return static::typeOptions()[$this->mail_type] ?? Str::headline($this->mail_type);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_DONATION_RECEIPT => 'Resit Sumbangan',
            self::TYPE_PAYMENT_MANUAL_DONOR => 'Bayaran Manual — Penderma',
            self::TYPE_PAYMENT_MANUAL_ADMIN => 'Bayaran Manual — Admin',
            self::TYPE_PAYMENT_COMPLETED_ADMIN => 'Bayaran Berjaya — Admin',
            self::TYPE_PAYMENT_REJECTED_DONOR => 'Bayaran Ditolak — Penderma',
            self::TYPE_PAYMENT_REJECTED_ADMIN => 'Bayaran Ditolak — Admin',
            self::TYPE_VOUCHER_ISSUED_APPLICANT => 'Baucar Dikeluarkan — Pemohon',
            self::TYPE_VOUCHER_ISSUED_ADMIN => 'Baucar Dikeluarkan — Admin',
            self::TYPE_ACKNOWLEDGEMENT_REQUEST => 'Pautan Akuan Penerimaan',
            self::TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT => 'Akuan Selesai — Penerima',
            self::TYPE_ACKNOWLEDGEMENT_COMPLETED_APPLICANT => 'Akuan Selesai — Pemohon',
            self::TYPE_PASSWORD_RESET => 'Tetapan Semula Kata Laluan',
            self::TYPE_EMAIL_VERIFICATION => 'Pengesahan Alamat E-mel',
        ];
    }
}
