<?php

namespace App\Policies;

use App\Models\PaymentVoucher;
use App\Models\User;

class PaymentVoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVoucherRole();
    }

    public function view(User $user, PaymentVoucher $voucher): bool
    {
        if ($user->isAdmin() || $user->isSecretary()) {
            return true;
        }

        if ($user->isTreasurer()) {
            return in_array($voucher->status, [
                PaymentVoucher::APPROVED,
                PaymentVoucher::ISSUED,
                PaymentVoucher::PAYMENT_CONFIRMED,
                PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
                PaymentVoucher::COMPLETED,
                PaymentVoucher::ARCHIVED,
            ], true);
        }

        return $user->role === 'ajk' && $voucher->applicant_id === $user->id;
    }

    public function viewSensitive(User $user, PaymentVoucher $voucher): bool
    {
        return $this->view($user, $voucher) && ($user->isAdmin() || $user->isTreasurer());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSecretary() || $user->role === 'ajk';
    }

    public function update(User $user, PaymentVoucher $voucher): bool
    {
        if (! in_array($voucher->status, [PaymentVoucher::DRAFT, PaymentVoucher::REJECTED], true)) {
            return false;
        }

        return $user->isAdmin() || $user->isSecretary()
            || ($user->role === 'ajk' && $voucher->applicant_id === $user->id);
    }

    public function submit(User $user, PaymentVoucher $voucher): bool
    {
        return $this->update($user, $voucher);
    }

    public function approve(User $user, PaymentVoucher $voucher): bool
    {
        return $voucher->status === PaymentVoucher::PENDING_APPROVAL
            && ($user->isAdmin() || ($user->isSecretary() && $voucher->applicant_id !== $user->id));
    }

    public function issue(User $user, PaymentVoucher $voucher): bool
    {
        return $voucher->status === PaymentVoucher::APPROVED
            && ($user->isAdmin() || $user->isSecretary());
    }

    public function confirmPayment(User $user, PaymentVoucher $voucher): bool
    {
        return in_array($voucher->status, [
            PaymentVoucher::ISSUED,
            PaymentVoucher::PAYMENT_CONFIRMED,
            PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
        ], true)
            && ($user->isAdmin() || $user->isTreasurer());
    }

    public function resendAcknowledgement(User $user, PaymentVoucher $voucher): bool
    {
        return $voucher->status === PaymentVoucher::AWAITING_ACKNOWLEDGEMENT
            && ($user->isAdmin() || $user->isTreasurer()
                || ($user->role === 'ajk' && $voucher->applicant_id === $user->id));
    }

    public function cancel(User $user, PaymentVoucher $voucher): bool
    {
        return ! in_array($voucher->status, [PaymentVoucher::COMPLETED, PaymentVoucher::ARCHIVED, PaymentVoucher::CANCELLED], true)
            && ($user->isAdmin() || $user->isSecretary());
    }

    public function reissue(User $user, PaymentVoucher $voucher): bool
    {
        return $voucher->status === PaymentVoucher::CANCELLED
            && ($user->isAdmin() || $user->isSecretary());
    }

    public function archive(User $user, PaymentVoucher $voucher): bool
    {
        return $user->isAdmin()
            && in_array($voucher->status, [PaymentVoucher::COMPLETED, PaymentVoucher::CANCELLED, PaymentVoucher::REJECTED], true);
    }
}
