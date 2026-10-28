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

        if ($user->canCreateVoucher() && $voucher->applicant_id === $user->id) {
            return true;
        }

        if ($user->canApproveVoucher() && $voucher->status === PaymentVoucher::PENDING_APPROVAL) {
            return true;
        }

        if ($user->canConfirmPayment() && in_array($voucher->status, [
            PaymentVoucher::ISSUED,
            PaymentVoucher::PAYMENT_CONFIRMED,
            PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
        ], true)) {
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
        return $this->view($user, $voucher) && $user->canConfirmPayment();
    }

    public function create(User $user): bool
    {
        return $user->canCreateVoucher();
    }

    public function update(User $user, PaymentVoucher $voucher): bool
    {
        if (! in_array($voucher->status, [PaymentVoucher::DRAFT, PaymentVoucher::REJECTED], true)) {
            return false;
        }

        return $user->isAdmin()
            || ($user->canCreateVoucher() && $voucher->applicant_id === $user->id);
    }

    public function submit(User $user, PaymentVoucher $voucher): bool
    {
        return $this->update($user, $voucher);
    }

    public function approve(User $user, PaymentVoucher $voucher): bool
    {
        return $voucher->status === PaymentVoucher::PENDING_APPROVAL
            && $voucher->applicant_id !== $user->id
            && $user->canApproveVoucher();
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
            && $user->canConfirmPayment();
    }

    public function resendAcknowledgement(User $user, PaymentVoucher $voucher): bool
    {
        return $voucher->status === PaymentVoucher::AWAITING_ACKNOWLEDGEMENT
            && ($user->canConfirmPayment()
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
