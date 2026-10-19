<?php

namespace App\Services;

use App\Models\DonationReceipt;
use App\Models\DonationReceiptAudit;
use App\Models\User;

class DonationReceiptAuditService
{
    public function record(
        DonationReceipt $receipt,
        string $action,
        ?User $actor = null,
        ?string $reason = null,
        string $actorType = 'admin',
    ): DonationReceiptAudit {
        return DonationReceiptAudit::query()->create([
            'donation_receipt_id' => $receipt->id,
            'receipt_no' => $receipt->receipt_no,
            'action' => $action,
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? $actorType : ($actorType === 'admin' ? 'system' : $actorType),
            'reason' => $reason,
        ]);
    }
}
