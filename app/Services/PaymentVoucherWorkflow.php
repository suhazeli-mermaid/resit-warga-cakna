<?php

namespace App\Services;

use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentVoucherWorkflow
{
    public function __construct(
        private readonly PaymentVoucherAuditService $audits,
        private readonly VoucherSnapshotService $snapshots,
        private readonly VoucherNumberService $numbers,
    ) {}

    public function submit(PaymentVoucher $voucher, User $actor): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::DRAFT, PaymentVoucher::REJECTED]);
            $signature = $this->snapshots->copyUserSignature($voucher->applicant, $voucher, 'applicant');
            $voucher->forceFill([
                'status' => PaymentVoucher::PENDING_APPROVAL,
                'submitted_at' => now(),
                'applicant_signature_snapshot_path' => $signature['path'],
                'applicant_signature_snapshot_checksum' => $signature['checksum'],
            ])->save();
            $this->audits->record($voucher, 'submitted', $actor);

            return $voucher->fresh();
        });
    }

    public function approve(PaymentVoucher $voucher, User $actor): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::PENDING_APPROVAL]);
            $signature = $this->snapshots->copyUserSignature($actor, $voucher, 'approver');
            $voucher->forceFill([
                'status' => PaymentVoucher::APPROVED,
                'approver_id' => $actor->id,
                'approved_at' => now(),
                'approver_signature_snapshot_path' => $signature['path'],
                'approver_signature_snapshot_checksum' => $signature['checksum'],
            ])->save();
            $this->audits->record($voucher, 'approved', $actor);

            return $voucher->fresh();
        });
    }

    public function reject(PaymentVoucher $voucher, User $actor, string $reason): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::PENDING_APPROVAL]);
            $voucher->forceFill(['status' => PaymentVoucher::REJECTED])->save();
            $this->audits->record($voucher, 'rejected', $actor, $reason);

            return $voucher->fresh();
        });
    }

    public function issue(PaymentVoucher $voucher, User $actor): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $voucher = PaymentVoucher::query()->with(['applicant', 'approver'])->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::APPROVED]);
            $this->snapshots->assertIssueReady($voucher);
            $stamp = $this->snapshots->copyOfficialStamp($voucher);
            $issuedAt = now();
            $applicantIdentity = $voucher->applicant->voucherIdentitySnapshot();
            $approverIdentity = $voucher->approver->voucherIdentitySnapshot();
            $voucher->forceFill([
                'status' => PaymentVoucher::ISSUED,
                'issued_at' => $issuedAt,
                'stamp_snapshot_path' => $stamp['path'],
                'stamp_snapshot_checksum' => $stamp['checksum'],
                'document_snapshot' => [
                    'voucher_no' => $voucher->voucher_no,
                    'voucher_date' => $voucher->voucher_date->toDateString(),
                    'committee_approval_date' => $voucher->committee_approval_date?->toDateString(),
                    'recipient_name' => $voucher->recipient_name,
                    'purpose' => $voucher->purpose,
                    'amount' => $voucher->amount,
                    // Legacy name keys remain for compatibility with documents already issued.
                    'applicant_name' => $applicantIdentity['full_name'],
                    'approver_name' => $approverIdentity['full_name'],
                    'applicant_salutation' => $applicantIdentity['salutation'],
                    'applicant_full_name' => $applicantIdentity['full_name'],
                    'applicant_organization_position' => $applicantIdentity['organization_position'],
                    'approver_salutation' => $approverIdentity['salutation'],
                    'approver_full_name' => $approverIdentity['full_name'],
                    'approver_organization_position' => $approverIdentity['organization_position'],
                    'approved_at' => $voucher->approved_at?->toIso8601String(),
                    'issued_at' => $issuedAt->toIso8601String(),
                    'applicant_signature_path' => $voucher->applicant_signature_snapshot_path,
                    'approver_signature_path' => $voucher->approver_signature_snapshot_path,
                    'stamp_path' => $stamp['path'],
                ],
            ])->save();
            $this->audits->record($voucher, 'issued', $actor);

            return $voucher->fresh(['applicant', 'approver']);
        });
    }

    public function confirmPayment(PaymentVoucher $voucher, User $actor, array $payment): array
    {
        return DB::transaction(function () use ($voucher, $actor, $payment) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $alreadyConfirmed = in_array($voucher->status, [
                PaymentVoucher::PAYMENT_CONFIRMED,
                PaymentVoucher::AWAITING_ACKNOWLEDGEMENT,
            ], true);

            if (! $alreadyConfirmed) {
                $this->expect($voucher, [PaymentVoucher::ISSUED]);
                $voucher->forceFill([
                    'status' => PaymentVoucher::PAYMENT_CONFIRMED,
                    'paid_amount' => $payment['paid_amount'],
                    'transaction_date' => $payment['transaction_date'],
                    'transaction_reference' => $payment['transaction_reference'],
                    'payment_proof_path' => $payment['payment_proof_path'] ?? null,
                    'payment_confirmed_by' => $actor->id,
                    'payment_confirmed_at' => now(),
                ])->save();
                $this->audits->record($voucher, 'payment_confirmed', $actor);
            }

            $acknowledgement = VoucherAcknowledgement::query()
                ->where('payment_voucher_id', $voucher->id)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($alreadyConfirmed && $acknowledgement) {
                $this->expireOtherPendingLinks($voucher->id, $acknowledgement->id);
                if ($voucher->status !== PaymentVoucher::AWAITING_ACKNOWLEDGEMENT) {
                    $voucher->forceFill(['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT])->save();
                }

                return [$voucher->fresh(), null, false];
            }

            $rawToken = Str::random(64);
            if ($acknowledgement) {
                $acknowledgement->forceFill([
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => now()->addDays(7),
                ])->save();
            } else {
                $acknowledgement = VoucherAcknowledgement::query()->create([
                    'payment_voucher_id' => $voucher->id,
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => now()->addDays(7),
                ]);
            }
            $this->expireOtherPendingLinks($voucher->id, $acknowledgement->id);

            $voucher->forceFill(['status' => PaymentVoucher::AWAITING_ACKNOWLEDGEMENT])->save();
            $this->audits->record($voucher, 'acknowledgement_link_created', $actor, metadata: [
                'acknowledgement_id' => $acknowledgement->id,
                'expires_at' => $acknowledgement->expires_at->toIso8601String(),
            ]);

            return [$voucher->fresh(), $rawToken, true];
        });
    }

    public function refreshAcknowledgementToken(PaymentVoucher $voucher, User $actor): array
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]);
            $acknowledgement = VoucherAcknowledgement::query()
                ->where('payment_voucher_id', $voucher->id)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $rawToken = Str::random(64);
            if ($acknowledgement) {
                $acknowledgement->forceFill([
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => now()->addDays(7),
                ])->save();
            } else {
                $acknowledgement = VoucherAcknowledgement::query()->create([
                    'payment_voucher_id' => $voucher->id,
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => now()->addDays(7),
                ]);
            }
            $this->expireOtherPendingLinks($voucher->id, $acknowledgement->id);
            $this->audits->record($voucher, 'acknowledgement_link_regenerated', $actor, metadata: [
                'acknowledgement_id' => $acknowledgement->id,
                'expires_at' => $acknowledgement->expires_at->toIso8601String(),
            ]);

            return [$voucher->fresh(), $rawToken];
        });
    }

    public function acknowledge(VoucherAcknowledgement $acknowledgement, string $name, ?string $notes): PaymentVoucher
    {
        return DB::transaction(function () use ($acknowledgement, $name, $notes) {
            $acknowledgement = VoucherAcknowledgement::query()->lockForUpdate()->findOrFail($acknowledgement->id);

            if (! $acknowledgement->isUsable()) {
                throw new RuntimeException('Pautan akuan telah luput atau telah digunakan.');
            }

            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($acknowledgement->payment_voucher_id);
            $this->expect($voucher, [PaymentVoucher::AWAITING_ACKNOWLEDGEMENT]);
            $acknowledgement->forceFill(['recipient_name' => $name, 'notes' => $notes, 'used_at' => now()])->save();
            $voucher->forceFill(['status' => PaymentVoucher::COMPLETED, 'completed_at' => now()])->save();
            $this->audits->record($voucher, 'acknowledgement_completed', reason: $notes, metadata: ['recipient_name' => $name], actorType: 'recipient');

            return $voucher->fresh(['applicant']);
        });
    }

    public function cancel(PaymentVoucher $voucher, User $actor, string $reason): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expectNot($voucher, [PaymentVoucher::COMPLETED, PaymentVoucher::ARCHIVED, PaymentVoucher::CANCELLED]);
            $voucher->forceFill(['status' => PaymentVoucher::CANCELLED, 'cancelled_at' => now()])->save();
            $this->audits->record($voucher, 'cancelled', $actor, $reason);

            return $voucher->fresh();
        });
    }

    public function reissue(PaymentVoucher $voucher, User $actor): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::CANCELLED]);
            $new = PaymentVoucher::query()->create([
                'voucher_no' => $this->numbers->next(now()),
                'voucher_date' => today(),
                'status' => PaymentVoucher::DRAFT,
                'applicant_id' => $voucher->applicant_id,
                'reissued_from_id' => $voucher->id,
                'recipient_name' => $voucher->recipient_name,
                'recipient_phone' => $voucher->recipient_phone,
                'recipient_email' => $voucher->recipient_email,
                'recipient_address' => $voucher->recipient_address,
                'recipient_ic' => $voucher->recipient_ic,
                'bank_name' => $voucher->bank_name,
                'bank_account_no' => $voucher->bank_account_no,
                'purpose' => $voucher->purpose,
                'amount' => $voucher->amount,
                'committee_approval_date' => $voucher->committee_approval_date,
            ]);
            $this->audits->record($voucher, 'reissued', $actor, metadata: ['new_voucher_no' => $new->voucher_no]);
            $this->audits->record($new, 'created', $actor, metadata: ['reissued_from' => $voucher->voucher_no]);

            return $new;
        });
    }

    public function archive(PaymentVoucher $voucher, User $actor, ?string $reason = null): PaymentVoucher
    {
        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $voucher = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $this->expect($voucher, [PaymentVoucher::COMPLETED, PaymentVoucher::CANCELLED, PaymentVoucher::REJECTED]);
            $voucher->forceFill(['status' => PaymentVoucher::ARCHIVED, 'archived_at' => now()])->save();
            $this->audits->record($voucher, 'archived', $actor, $reason);

            return $voucher->fresh();
        });
    }

    private function expect(PaymentVoucher $voucher, array $states): void
    {
        if (! in_array($voucher->status, $states, true)) {
            throw new RuntimeException('Status baucar telah berubah. Muat semula halaman dan cuba lagi.');
        }
    }

    private function expectNot(PaymentVoucher $voucher, array $states): void
    {
        if (in_array($voucher->status, $states, true)) {
            throw new RuntimeException('Baucar dalam status ini tidak boleh dibatalkan.');
        }
    }

    private function expireOtherPendingLinks(int $voucherId, int $activeAcknowledgementId): void
    {
        VoucherAcknowledgement::query()
            ->where('payment_voucher_id', $voucherId)
            ->whereNull('used_at')
            ->whereKeyNot($activeAcknowledgementId)
            ->where('expires_at', '>', now())
            ->update([
                'expires_at' => now()->subSecond(),
                'updated_at' => now(),
            ]);
    }
}
