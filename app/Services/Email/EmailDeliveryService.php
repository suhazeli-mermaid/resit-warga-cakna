<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class EmailDeliveryService
{
    public function __construct(private readonly EmailLogSanitizer $sanitizer) {}

    public function sendMailable(
        string $recipientEmail,
        ?string $recipientName,
        Mailable $mail,
        string $mailType,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $relatedReference = null,
        ?EmailLog $resendOf = null,
        ?User $resentBy = null,
    ): bool {
        $subject = (string) ($mail->envelope()->subject ?? 'E-mel Warga Cakna');

        return $this->deliver(
            recipientEmail: $recipientEmail,
            recipientName: $recipientName,
            subject: $subject,
            mailType: $mailType,
            relatedType: $relatedType,
            relatedId: $relatedId,
            relatedReference: $relatedReference,
            resendOf: $resendOf,
            resentBy: $resentBy,
            sender: fn () => Mail::to($recipientEmail)->send($mail),
        );
    }

    public function sendNotification(
        User $recipient,
        LaravelNotification $notification,
        string $subject,
        string $mailType,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $relatedReference = null,
        ?EmailLog $resendOf = null,
        ?User $resentBy = null,
        bool $throwOnFailure = false,
    ): bool {
        return $this->deliver(
            recipientEmail: (string) $recipient->email,
            recipientName: $recipient->name,
            subject: $subject,
            mailType: $mailType,
            relatedType: $relatedType,
            relatedId: $relatedId,
            relatedReference: $relatedReference,
            resendOf: $resendOf,
            resentBy: $resentBy,
            sender: fn () => Notification::sendNow($recipient, $notification),
            throwOnFailure: $throwOnFailure,
        );
    }

    private function deliver(
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $mailType,
        ?string $relatedType,
        ?int $relatedId,
        ?string $relatedReference,
        ?EmailLog $resendOf,
        ?User $resentBy,
        callable $sender,
        bool $throwOnFailure = false,
    ): bool {
        try {
            $log = EmailLog::query()->create([
                'mail_type' => mb_substr($mailType, 0, 100),
                'recipient_name' => filled($recipientName) ? mb_substr(trim((string) $recipientName), 0, 255) : null,
                'recipient_email' => mb_substr(trim($recipientEmail), 0, 254),
                'subject' => mb_substr(trim($subject), 0, 500),
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'related_reference' => filled($relatedReference) ? mb_substr((string) $relatedReference, 0, 150) : null,
                'status' => EmailLog::PENDING,
                'attempted_at' => now(),
                'resend_of_id' => $resendOf?->id,
                'resent_by' => $resentBy?->id,
            ]);
        } catch (Throwable) {
            if ($throwOnFailure) {
                throw new RuntimeException('Audit penghantaran e-mel tidak dapat dicipta.');
            }

            return false;
        }

        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || ! $this->mailCanBeSentSafely()) {
            $this->markFailed($log, 'Mailer atau alamat penerima tidak selamat untuk penghantaran.');

            if ($throwOnFailure) {
                throw new RuntimeException('Penghantaran e-mel tidak tersedia.');
            }

            return false;
        }

        try {
            $sender();
        } catch (Throwable $exception) {
            $this->markFailed($log, $exception);

            if ($throwOnFailure) {
                throw new RuntimeException('Penghantaran e-mel gagal.');
            }

            return false;
        }

        try {
            $log->forceFill([
                'status' => EmailLog::SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_message' => null,
            ])->save();
        } catch (Throwable) {
            // The transport accepted the message. Leave PENDING if audit finalization fails.
        }

        return true;
    }

    private function markFailed(EmailLog $log, Throwable|string $failure): void
    {
        try {
            $log->forceFill([
                'status' => EmailLog::FAILED,
                'failed_at' => now(),
                'sent_at' => null,
                'failure_message' => $this->sanitizer->failure($failure),
            ])->save();
        } catch (Throwable) {
            // Business state remains authoritative even when audit persistence is unavailable.
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

    /** @param array<string> $visited */
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
