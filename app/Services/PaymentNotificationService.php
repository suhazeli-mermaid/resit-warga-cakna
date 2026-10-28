<?php

namespace App\Services;

use App\Mail\DonationReceiptMail;
use App\Mail\PaymentStatusMail;
use App\Models\DonationReceipt;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Email\EmailDeliveryService;

class PaymentNotificationService
{
    public function __construct(private readonly EmailDeliveryService $delivery) {}

    public function manualReceived(DonationReceipt $receipt): void
    {
        $this->sendToDonor($receipt, PaymentStatusMail::MANUAL_RECEIVED, EmailLog::TYPE_PAYMENT_MANUAL_DONOR);
        $this->sendToAdmin($receipt, PaymentStatusMail::MANUAL_RECEIVED, EmailLog::TYPE_PAYMENT_MANUAL_ADMIN);
    }

    public function completed(DonationReceipt $receipt): void
    {
        if ($receipt->donor_email) {
            $this->delivery->sendMailable(
                $receipt->donor_email,
                $receipt->donor_name,
                new DonationReceiptMail($receipt),
                EmailLog::TYPE_DONATION_RECEIPT,
                EmailLog::RELATED_RECEIPT,
                $receipt->id,
                $receipt->receipt_no,
            );
        }

        $this->sendToAdmin($receipt, PaymentStatusMail::PAYMENT_COMPLETED, EmailLog::TYPE_PAYMENT_COMPLETED_ADMIN);
    }

    public function rejected(DonationReceipt $receipt): void
    {
        $this->sendToDonor($receipt, PaymentStatusMail::PAYMENT_REJECTED, EmailLog::TYPE_PAYMENT_REJECTED_DONOR);
        $this->sendToAdmin($receipt, PaymentStatusMail::PAYMENT_REJECTED, EmailLog::TYPE_PAYMENT_REJECTED_ADMIN);
    }

    public function resendReceipt(
        DonationReceipt $receipt,
        ?EmailLog $resendOf = null,
        ?User $resentBy = null,
    ): bool {
        if (! $receipt->donor_email || ! $receipt->isCompleted()) {
            return false;
        }

        return $this->delivery->sendMailable(
            $receipt->donor_email,
            $receipt->donor_name,
            new DonationReceiptMail($receipt),
            EmailLog::TYPE_DONATION_RECEIPT,
            EmailLog::RELATED_RECEIPT,
            $receipt->id,
            $receipt->receipt_no,
            $resendOf,
            $resentBy,
        );
    }

    public function resendFromLog(EmailLog $log, User $actor): bool
    {
        $receipt = DonationReceipt::query()->findOrFail($log->related_id);

        return match ($log->mail_type) {
            EmailLog::TYPE_DONATION_RECEIPT => $this->resendReceipt($receipt, $log, $actor),
            EmailLog::TYPE_PAYMENT_MANUAL_DONOR => $receipt->isManualPayment() && $receipt->isPending()
                ? $this->sendToDonor($receipt, PaymentStatusMail::MANUAL_RECEIVED, $log->mail_type, $log, $actor)
                : false,
            EmailLog::TYPE_PAYMENT_MANUAL_ADMIN => $receipt->isManualPayment() && $receipt->isPending()
                ? $this->sendToAdmin($receipt, PaymentStatusMail::MANUAL_RECEIVED, $log->mail_type, $log, $actor)
                : false,
            EmailLog::TYPE_PAYMENT_COMPLETED_ADMIN => $receipt->isCompleted()
                ? $this->sendToAdmin($receipt, PaymentStatusMail::PAYMENT_COMPLETED, $log->mail_type, $log, $actor)
                : false,
            EmailLog::TYPE_PAYMENT_REJECTED_DONOR => $receipt->status === DonationReceipt::STATUS_REJECTED
                ? $this->sendToDonor($receipt, PaymentStatusMail::PAYMENT_REJECTED, $log->mail_type, $log, $actor)
                : false,
            EmailLog::TYPE_PAYMENT_REJECTED_ADMIN => $receipt->status === DonationReceipt::STATUS_REJECTED
                ? $this->sendToAdmin($receipt, PaymentStatusMail::PAYMENT_REJECTED, $log->mail_type, $log, $actor)
                : false,
            default => false,
        };
    }

    private function sendToDonor(
        DonationReceipt $receipt,
        string $event,
        string $mailType,
        ?EmailLog $resendOf = null,
        ?User $resentBy = null,
    ): bool {
        if (! $receipt->donor_email) {
            return false;
        }

        return $this->delivery->sendMailable(
            $receipt->donor_email,
            $receipt->donor_name,
            new PaymentStatusMail($receipt, $event, 'donor'),
            $mailType,
            EmailLog::RELATED_RECEIPT,
            $receipt->id,
            $receipt->receipt_no,
            $resendOf,
            $resentBy,
        );
    }

    private function sendToAdmin(
        DonationReceipt $receipt,
        string $event,
        string $mailType,
        ?EmailLog $resendOf = null,
        ?User $resentBy = null,
    ): bool {
        $adminEmail = $this->adminEmail();
        if ($adminEmail === null) {
            return false;
        }

        if ($resendOf && strcasecmp($resendOf->recipient_email, $adminEmail) !== 0) {
            return false;
        }

        $adminName = User::query()->where('role', 'admin')->where('email', $adminEmail)->value('name');

        return $this->delivery->sendMailable(
            $adminEmail,
            $adminName,
            new PaymentStatusMail($receipt, $event, 'admin'),
            $mailType,
            EmailLog::RELATED_RECEIPT,
            $receipt->id,
            $receipt->receipt_no,
            $resendOf,
            $resentBy,
        );
    }

    private function adminEmail(): ?string
    {
        $email = trim((string) (
            Setting::query()->where('key', 'admin_notification_email')->value('value')
            ?: config('services.notifications.admin_email')
        ));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
