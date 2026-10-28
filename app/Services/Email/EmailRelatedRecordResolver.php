<?php

namespace App\Services\Email;

use App\Models\DonationReceipt;
use App\Models\EmailLog;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use Illuminate\Support\Facades\Gate;

class EmailRelatedRecordResolver
{
    /** @return array{label: string, url: string}|null */
    public function link(EmailLog $log, User $user): ?array
    {
        if ($log->related_type === EmailLog::RELATED_RECEIPT) {
            $receipt = DonationReceipt::query()->find($log->related_id);

            return $receipt && $user->isAdmin()
                ? ['label' => 'Lihat Resit', 'url' => route('receipts.show', $receipt->id)]
                : null;
        }

        if ($log->related_type === EmailLog::RELATED_VOUCHER) {
            $voucher = PaymentVoucher::query()->find($log->related_id);

            return $voucher && Gate::forUser($user)->allows('view', $voucher)
                ? ['label' => 'Lihat Baucar', 'url' => route('vouchers.show', $voucher)]
                : null;
        }

        if ($log->related_type === EmailLog::RELATED_ACKNOWLEDGEMENT) {
            $acknowledgement = VoucherAcknowledgement::query()->with('voucher')->find($log->related_id);

            return $acknowledgement && Gate::forUser($user)->allows('view', $acknowledgement)
                ? ['label' => 'Lihat Surat Akuan', 'url' => route('vouchers.acknowledgements.show', $acknowledgement)]
                : null;
        }

        if ($log->related_type === EmailLog::RELATED_USER && $user->isAdmin()) {
            return ['label' => 'Pengurusan Pengguna', 'url' => route('users.index')];
        }

        return null;
    }
}
