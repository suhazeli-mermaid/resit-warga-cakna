<?php

namespace App\Services;

use App\Models\PaymentVoucher;
use App\Models\PaymentVoucherAudit;
use App\Models\User;

class PaymentVoucherAuditService
{
    public function record(
        PaymentVoucher $voucher,
        string $event,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
        string $actorType = 'user',
    ): PaymentVoucherAudit {
        return PaymentVoucherAudit::query()->create([
            'payment_voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'event' => $event,
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? $actorType : 'system',
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
