<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoucherAcknowledgement;

class VoucherAcknowledgementPolicy
{
    public function __construct(
        private readonly PaymentVoucherPolicy $vouchers,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->hasVoucherRole();
    }

    public function view(User $user, VoucherAcknowledgement $acknowledgement): bool
    {
        return $this->vouchers->view($user, $acknowledgement->voucher);
    }

    public function resend(User $user, VoucherAcknowledgement $acknowledgement): bool
    {
        return $this->vouchers->resendAcknowledgement($user, $acknowledgement->voucher);
    }
}
