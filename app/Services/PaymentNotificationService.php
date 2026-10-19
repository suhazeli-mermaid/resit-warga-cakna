<?php

namespace App\Services;

use App\Mail\DonationReceiptMail;
use App\Mail\PaymentStatusMail;
use App\Models\DonationReceipt;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PaymentNotificationService
{
    public function manualReceived(DonationReceipt $receipt): void
    {
        if (! $this->mailCanBeSentSafely()) {
            return;
        }

        $this->sendToDonor($receipt, PaymentStatusMail::MANUAL_RECEIVED);
        $this->sendToAdmin($receipt, PaymentStatusMail::MANUAL_RECEIVED);
    }

    public function completed(DonationReceipt $receipt): void
    {
        if (! $this->mailCanBeSentSafely()) {
            return;
        }

        if ($receipt->donor_email) {
            $this->sendSafely(
                $receipt->donor_email,
                new DonationReceiptMail($receipt),
            );
        }

        $this->sendToAdmin($receipt, PaymentStatusMail::PAYMENT_COMPLETED);
    }

    public function rejected(DonationReceipt $receipt): void
    {
        if (! $this->mailCanBeSentSafely()) {
            return;
        }

        $this->sendToDonor($receipt, PaymentStatusMail::PAYMENT_REJECTED);
        $this->sendToAdmin($receipt, PaymentStatusMail::PAYMENT_REJECTED);
    }

    public function resendReceipt(DonationReceipt $receipt): bool
    {
        if (! $this->mailCanBeSentSafely() || ! $receipt->donor_email || ! $receipt->isCompleted()) {
            return false;
        }

        return $this->sendSafely(
            $receipt->donor_email,
            new DonationReceiptMail($receipt),
        );
    }

    private function sendToDonor(DonationReceipt $receipt, string $event): void
    {
        if (! $receipt->donor_email) {
            return;
        }

        $this->sendSafely(
            $receipt->donor_email,
            new PaymentStatusMail($receipt, $event, 'donor'),
        );
    }

    private function sendToAdmin(DonationReceipt $receipt, string $event): void
    {
        $adminEmail = trim((string) (
            Setting::query()->where('key', 'admin_notification_email')->value('value')
            ?: config('services.notifications.admin_email')
        ));

        if ($adminEmail === '' || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $this->sendSafely(
            $adminEmail,
            new PaymentStatusMail($receipt, $event, 'admin'),
        );
    }

    private function sendSafely(string $recipient, Mailable $mail): bool
    {
        try {
            Mail::to($recipient)->send($mail);

            return true;
        } catch (Throwable) {
            // Payment state must remain authoritative even if SMTP is unavailable.
            return false;
        }
    }

    private function mailCanBeSentSafely(): bool
    {
        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport", $mailer);

        if (app()->environment('testing') && $transport === 'array') {
            return true;
        }

        return $this->mailerAvoidsSensitiveLogging($mailer);
    }

    /**
     * @param  array<string>  $visited
     */
    private function mailerAvoidsSensitiveLogging(string $mailer, array $visited = []): bool
    {
        if ($mailer === '' || in_array($mailer, $visited, true)) {
            return false;
        }

        $visited[] = $mailer;
        $configuration = config("mail.mailers.{$mailer}");

        if (! is_array($configuration)) {
            return false;
        }

        $transport = $configuration['transport'] ?? $mailer;

        if (in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            foreach ($configuration['mailers'] ?? [] as $nestedMailer) {
                if (! $this->mailerAvoidsSensitiveLogging((string) $nestedMailer, $visited)) {
                    return false;
                }
            }
        }

        return true;
    }
}
