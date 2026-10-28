<?php

namespace App\Services;

use App\Mail\VoucherNotificationMail;
use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class VoucherNotificationService
{
    public function issued(PaymentVoucher $voucher): void
    {
        $recipients = collect([$voucher->applicant?->email])
            ->merge($this->adminEmails())
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique();

        foreach ($recipients as $email) {
            $this->sendSafely($email, new VoucherNotificationMail($voucher, 'issued'));
        }
    }

    public function paymentConfirmed(PaymentVoucher $voucher, string $rawToken): bool
    {
        if (filter_var($voucher->recipient_email, FILTER_VALIDATE_EMAIL)) {
            return $this->sendSafely(
                $voucher->recipient_email,
                new VoucherNotificationMail($voucher, 'payment_confirmed', $rawToken),
            );
        }

        return false;
    }

    public function acknowledgementCompleted(PaymentVoucher $voucher): void
    {
        foreach (collect([$voucher->recipient_email, $voucher->applicant?->email])->filter()->unique() as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->sendSafely($email, new VoucherNotificationMail($voucher, 'acknowledgement_completed'));
            }
        }
    }

    private function adminEmails()
    {
        $configured = Setting::query()->where('key', 'admin_notification_email')->value('value')
            ?: config('services.notifications.admin_email');

        return User::query()->where('role', 'admin')->pluck('email')->push($configured);
    }

    private function sendSafely(string $recipient, Mailable $mail): bool
    {
        if (! $this->mailCanBeSentSafely()) {
            return false;
        }

        try {
            Mail::to($recipient)->send($mail);

            return true;
        } catch (Throwable) {
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
