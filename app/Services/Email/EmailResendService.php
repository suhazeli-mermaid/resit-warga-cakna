<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use App\Services\PaymentNotificationService;
use App\Services\PaymentVoucherWorkflow;
use App\Services\VoucherNotificationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use RuntimeException;

class EmailResendService
{
    public function __construct(
        private readonly EmailDeliveryService $delivery,
        private readonly PaymentNotificationService $payments,
        private readonly VoucherNotificationService $vouchers,
        private readonly PaymentVoucherWorkflow $voucherWorkflow,
    ) {}

    public function resend(EmailLog $original, User $actor): bool
    {
        if ($original->resends()->where('created_at', '>=', now()->subSeconds(30))->exists()
            || ! Cache::add('email-log-resend:'.$original->public_id, true, now()->addSeconds(30))) {
            throw new RuntimeException('Permintaan hantar semula baru sahaja diproses. Sila tunggu sebelum mencuba lagi.');
        }

        return match ($original->mail_type) {
            EmailLog::TYPE_DONATION_RECEIPT,
            EmailLog::TYPE_PAYMENT_MANUAL_DONOR,
            EmailLog::TYPE_PAYMENT_MANUAL_ADMIN,
            EmailLog::TYPE_PAYMENT_COMPLETED_ADMIN,
            EmailLog::TYPE_PAYMENT_REJECTED_DONOR,
            EmailLog::TYPE_PAYMENT_REJECTED_ADMIN => $this->payments->resendFromLog($original, $actor),

            EmailLog::TYPE_VOUCHER_ISSUED_APPLICANT,
            EmailLog::TYPE_VOUCHER_ISSUED_ADMIN => $this->vouchers->resendOrdinary($original, $actor),

            EmailLog::TYPE_ACKNOWLEDGEMENT_REQUEST => $this->resendAcknowledgementRequest($original, $actor),

            EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_RECIPIENT,
            EmailLog::TYPE_ACKNOWLEDGEMENT_COMPLETED_APPLICANT => $this->vouchers->resendCompletedAcknowledgement($original, $actor),

            EmailLog::TYPE_PASSWORD_RESET => $this->resendPasswordReset($original, $actor),
            EmailLog::TYPE_EMAIL_VERIFICATION => $this->resendEmailVerification($original, $actor),
            default => false,
        };
    }

    private function resendAcknowledgementRequest(EmailLog $original, User $actor): bool
    {
        $acknowledgement = VoucherAcknowledgement::query()->with('voucher')->findOrFail($original->related_id);
        if ($acknowledgement->used_at !== null || ! $acknowledgement->voucher) {
            return false;
        }

        [$voucher, $rawToken] = $this->voucherWorkflow->refreshAcknowledgementToken(
            $acknowledgement->voucher,
            $actor,
        );

        return $this->vouchers->paymentConfirmed($voucher, $rawToken, $original, $actor);
    }

    private function resendPasswordReset(EmailLog $original, User $actor): bool
    {
        $user = User::query()->findOrFail($original->related_id);
        $token = Password::broker()->createToken($user);

        return $this->delivery->sendNotification(
            recipient: $user,
            notification: new ResetPassword($token),
            subject: __('Reset Password Notification'),
            mailType: EmailLog::TYPE_PASSWORD_RESET,
            relatedType: EmailLog::RELATED_USER,
            relatedId: $user->id,
            relatedReference: $user->email,
            resendOf: $original,
            resentBy: $actor,
        );
    }

    private function resendEmailVerification(EmailLog $original, User $actor): bool
    {
        $user = User::query()->findOrFail($original->related_id);
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        return $this->delivery->sendNotification(
            recipient: $user,
            notification: new VerifyEmail,
            subject: __('Verify Email Address'),
            mailType: EmailLog::TYPE_EMAIL_VERIFICATION,
            relatedType: EmailLog::RELATED_USER,
            relatedId: $user->id,
            relatedReference: $user->email,
            resendOf: $original,
            resentBy: $actor,
        );
    }
}
