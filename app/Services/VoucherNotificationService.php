<?php

namespace App\Services;

use App\Mail\VoucherNotificationMail;
use App\Models\EmailLog;
use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use App\Services\Email\EmailDeliveryService;

class VoucherNotificationService
{
    public function __construct(private readonly EmailDeliveryService $delivery) {}

    public function issued(PaymentVoucher $voucher): void
    {
        $voucher->loadMissing('applicant');
        $applicantEmail = $voucher->applicant?->email;
        $recipients = collect([$applicantEmail])
            ->merge($this->adminEmails())
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique(fn ($email) => strtolower((string) $email));

        foreach ($recipients as $email) {
            $isApplicant = $applicantEmail && strcasecmp((string) $email, $applicantEmail) === 0;
            $this->delivery->sendMailable(
                $email,
                $isApplicant ? $voucher->applicant?->name : $this->nameForEmail($email),
                new VoucherNotificationMail($voucher, 'issued'),
                $isApplicant ? EmailLog::TYPE_VOUCHER_ISSUED_APPLICANT : EmailLog::TYPE_VOUCHER_ISSUED_ADMIN,
                EmailLog::RELATED_VOUCHER,
                $voucher->id,
                $voucher->voucher_no,
            );
        }
    }

    public function paymentConfirmed(
        PaymentVoucher $voucher,
        string $rawToken,
        ?EmailLog $resendOf = null,
        ?User $resentBy = null,
    ): bool {
        if (! filter_var($voucher->recipient_email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $acknowledgement = $voucher->acknowledgements()
            ->whereNull('used_at')
            ->latest('id')
            ->first();
        if (! $acknowledgement) {
            return false;
        }

        return $this->delivery->sendMailable(
            $voucher->recipient_email,
            $voucher->recipient_name,
            new VoucherNotificationMail($voucher, 'payment_confirmed', $rawToken),
            EmailLog::TYPE_ACKNOWLEDGEMENT_REQUEST,
            EmailLog::RELATED_ACKNOWLEDGEMENT,
            $acknowledgement->id,
            $acknowledgement->acknowledgement_no,
            $resendOf,
            $resentBy,
        );
    }

    public function acknowledgementCompleted(PaymentVoucher $voucher): void
    {
        $voucher->loadMissing('applicant');
        $acknowledgement = $voucher->acknowledgements()->whereNotNull('used_at')->latest('used_at')->first();
        if (! $acknowledgement) {
            return;
        }

        $recipients = collect([
            [
                'email' => $voucher->recipient_email,
                'name' => $voucher->recipient_name,
                'type' => EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT,
            ],
            [
                'email' => $voucher->applicant?->email,
                'name' => $voucher->applicant?->name,
                'type' => EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_APPLICANT,
            ],
        ])->filter(fn ($recipient) => filter_var($recipient['email'], FILTER_VALIDATE_EMAIL))
            ->unique(fn ($recipient) => strtolower((string) $recipient['email']));

        foreach ($recipients as $recipient) {
            $this->delivery->sendMailable(
                $recipient['email'],
                $recipient['name'],
                new VoucherNotificationMail($voucher, 'acknowledgement_completed'),
                $recipient['type'],
                EmailLog::RELATED_ACKNOWLEDGEMENT,
                $acknowledgement->id,
                $acknowledgement->acknowledgement_no,
            );
        }
    }

    public function resendOrdinary(EmailLog $log, User $actor): bool
    {
        $voucher = PaymentVoucher::query()->with('applicant')->findOrFail($log->related_id);

        if (! $voucher->isIssuedDocument()) {
            return false;
        }

        if ($log->mail_type === EmailLog::TYPE_VOUCHER_ISSUED_APPLICANT && $voucher->applicant?->email) {
            return $this->sendVoucher(
                $voucher,
                $voucher->applicant->email,
                $voucher->applicant->name,
                'issued',
                $log->mail_type,
                $log,
                $actor,
            );
        }

        if ($log->mail_type === EmailLog::TYPE_VOUCHER_ISSUED_ADMIN && $this->isCurrentAdminEmail($log->recipient_email)) {
            return $this->sendVoucher(
                $voucher,
                $log->recipient_email,
                $this->nameForEmail($log->recipient_email),
                'issued',
                $log->mail_type,
                $log,
                $actor,
            );
        }

        return false;
    }

    public function resendCompletedAcknowledgement(EmailLog $log, User $actor): bool
    {
        $acknowledgement = VoucherAcknowledgement::query()->with('voucher.applicant')->findOrFail($log->related_id);
        $voucher = $acknowledgement->voucher;
        if (! $acknowledgement->used_at) {
            return false;
        }

        if ($log->mail_type === EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT) {
            return $this->sendAcknowledgementCompleted(
                $voucher,
                $acknowledgement->id,
                $acknowledgement->acknowledgement_no,
                $voucher->recipient_email,
                $voucher->recipient_name,
                $log->mail_type,
                $log,
                $actor,
            );
        }

        if ($log->mail_type === EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_APPLICANT
            && $voucher->applicant?->email) {
            return $this->sendAcknowledgementCompleted(
                $voucher,
                $acknowledgement->id,
                $acknowledgement->acknowledgement_no,
                $voucher->applicant->email,
                $voucher->applicant->name,
                $log->mail_type,
                $log,
                $actor,
            );
        }

        return false;
    }

    private function sendVoucher(
        PaymentVoucher $voucher,
        string $email,
        ?string $name,
        string $event,
        string $mailType,
        EmailLog $resendOf,
        User $resentBy,
    ): bool {
        return $this->delivery->sendMailable(
            $email,
            $name,
            new VoucherNotificationMail($voucher, $event),
            $mailType,
            EmailLog::RELATED_VOUCHER,
            $voucher->id,
            $voucher->voucher_no,
            $resendOf,
            $resentBy,
        );
    }

    private function sendAcknowledgementCompleted(
        PaymentVoucher $voucher,
        int $acknowledgementId,
        string $reference,
        string $email,
        ?string $name,
        string $mailType,
        EmailLog $resendOf,
        User $resentBy,
    ): bool {
        return $this->delivery->sendMailable(
            $email,
            $name,
            new VoucherNotificationMail($voucher, 'acknowledgement_completed'),
            $mailType,
            EmailLog::RELATED_ACKNOWLEDGEMENT,
            $acknowledgementId,
            $reference,
            $resendOf,
            $resentBy,
        );
    }

    private function adminEmails()
    {
        $configured = Setting::query()->where('key', 'admin_notification_email')->value('value')
            ?: config('services.notifications.admin_email');

        return User::query()->where('role', 'admin')->pluck('email')->push($configured);
    }

    private function isCurrentAdminEmail(string $email): bool
    {
        return $this->adminEmails()->filter()->contains(fn ($candidate) => strcasecmp((string) $candidate, $email) === 0);
    }

    private function nameForEmail(string $email): ?string
    {
        return User::query()->where('email', $email)->value('name');
    }
}
