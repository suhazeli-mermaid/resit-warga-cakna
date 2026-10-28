<?php

namespace App\Services;

use App\Exceptions\VoucherReadinessException;
use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VoucherSnapshotService
{
    public function copyUserSignature(User $user, PaymentVoucher $voucher, string $kind): array
    {
        $this->assertUserReady($user, $kind);

        return $this->copyVerified($user->digital_signature_path, $voucher, $kind);
    }

    public function copyOfficialStamp(PaymentVoucher $voucher): array
    {
        $path = Setting::query()->where('key', 'organization_official_stamp_path')->value('value');

        if (! $this->hasOfficialStamp()) {
            throw new VoucherReadinessException('official_stamp', 'Cop rasmi Warga Cakna belum dimuat naik.');
        }

        return $this->copyVerified($path, $voucher, 'stamp');
    }

    public function hasUserSignature(?User $user): bool
    {
        return $user?->hasDigitalSignature() === true;
    }

    public function userReadiness(?User $user): array
    {
        $fullName = $user?->hasValidIndividualName() === true;
        $position = $user?->hasOrganizationPosition() === true;
        $signature = $this->hasUserSignature($user);

        return [
            'full_name' => $fullName,
            'position' => $position,
            'signature' => $signature,
            'ready' => $fullName && $position && $signature,
        ];
    }

    public function issueReadiness(PaymentVoucher $voucher): array
    {
        $applicantIdentity = $voucher->applicant?->hasCompleteVoucherIdentity() === true;
        $approverIdentity = $voucher->approver?->hasCompleteVoucherIdentity() === true;
        $applicantSignature = $this->hasVerifiedSnapshot(
            $voucher->applicant_signature_snapshot_path,
            $voucher->applicant_signature_snapshot_checksum,
        );
        $approverSignature = $this->hasVerifiedSnapshot(
            $voucher->approver_signature_snapshot_path,
            $voucher->approver_signature_snapshot_checksum,
        );

        return [
            'applicant_identity' => $applicantIdentity,
            'approver_identity' => $approverIdentity,
            'applicant_signature' => $applicantSignature,
            'approver_signature' => $approverSignature,
            'ready' => $applicantIdentity && $approverIdentity && $applicantSignature && $approverSignature,
        ];
    }

    public function assertIssueReady(PaymentVoucher $voucher): void
    {
        $this->assertUserIdentityReady($voucher->applicant, 'applicant');
        $this->assertUserIdentityReady($voucher->approver, 'approver');

        $readiness = $this->issueReadiness($voucher);
        if (! $readiness['applicant_signature']) {
            throw new VoucherReadinessException(
                'applicant_signature',
                'Snapshot tandatangan pemohon tidak tersedia atau tidak sah. Hantar semula Baucar melalui aliran kelulusan yang betul.',
            );
        }
        if (! $readiness['approver_signature']) {
            throw new VoucherReadinessException(
                'approver_signature',
                'Snapshot tandatangan pelulus tidak tersedia atau tidak sah. Luluskan semula Baucar melalui aliran yang betul.',
            );
        }
    }

    public function hasOfficialStamp(): bool
    {
        $path = Setting::query()->where('key', 'organization_official_stamp_path')->value('value');

        return filled($path) && Storage::disk('local')->exists($path);
    }

    /**
     * Return verified snapshot bytes as data URIs for browser and PDF rendering.
     * The stored image is never resized, recompressed, cropped, or overwritten.
     */
    public function documentImages(PaymentVoucher $voucher): array
    {
        $paths = [
            'applicant' => $voucher->applicant_signature_snapshot_path,
            'approver' => $voucher->approver_signature_snapshot_path,
            'stamp' => $voucher->stamp_snapshot_path,
        ];

        return collect($paths)->map(function (?string $path): ?string {
            if (! $path || ! Storage::disk('local')->exists($path)) {
                return null;
            }

            $contents = Storage::disk('local')->get($path);
            $image = @getimagesizefromstring($contents);
            $mime = is_array($image) ? ($image['mime'] ?? null) : null;

            if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        })->all();
    }

    private function copyVerified(string $source, PaymentVoucher $voucher, string $kind): array
    {
        $disk = Storage::disk('local');
        $contents = $disk->get($source);
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $extension = in_array($extension, ['png', 'jpg', 'jpeg'], true) ? $extension : 'png';
        $target = "vouchers/{$voucher->id}/snapshots/{$kind}-".Str::uuid().".{$extension}";

        if (! $disk->put($target, $contents)) {
            throw new RuntimeException('Salinan imej dokumen gagal disimpan.');
        }

        $checksum = hash('sha256', $contents);

        if (! hash_equals($checksum, hash('sha256', $disk->get($target)))) {
            $disk->delete($target);
            throw new RuntimeException('Pengesahan checksum salinan imej gagal.');
        }

        return ['path' => $target, 'checksum' => $checksum];
    }

    private function assertUserReady(User $user, string $kind): void
    {
        $this->assertUserIdentityReady($user, $kind);

        if ($this->hasUserSignature($user)) {
            return;
        }

        if ($kind === 'applicant') {
            throw new VoucherReadinessException(
                'applicant_signature',
                'Tandatangan digital anda belum dimuat naik. Sila lengkapkan tandatangan digital di Profil Pengguna sebelum menghantar Baucar untuk kelulusan.',
            );
        }

        throw new VoucherReadinessException(
            'approver_signature',
            'Tandatangan digital pelulus belum dimuat naik. Sila lengkapkan tandatangan digital di Profil Pengguna sebelum meluluskan Baucar.',
        );
    }

    private function assertUserIdentityReady(?User $user, string $kind): void
    {
        $label = $kind === 'applicant' ? 'pemohon' : 'pelulus';

        if (! $user?->hasValidIndividualName()) {
            throw new VoucherReadinessException(
                $kind.'_identity',
                "Nama Penuh individu bagi {$label} belum lengkap atau masih menggunakan nama organisasi. Lengkapkan Profil Pengguna sebelum meneruskan workflow Baucar.",
            );
        }

        if (! $user->hasOrganizationPosition()) {
            throw new VoucherReadinessException(
                $kind.'_position',
                "Jawatan Warga Cakna bagi {$label} belum ditetapkan. Minta Admin melengkapkan profil pengguna sebelum meneruskan workflow Baucar.",
            );
        }
    }

    private function hasVerifiedSnapshot(?string $path, ?string $checksum): bool
    {
        if (! filled($path) || ! filled($checksum) || ! Storage::disk('local')->exists($path)) {
            return false;
        }

        return hash_equals($checksum, hash('sha256', Storage::disk('local')->get($path)));
    }
}
