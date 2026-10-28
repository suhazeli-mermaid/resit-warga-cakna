<?php

namespace Tests\Feature;

use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\User;
use App\Models\VoucherAcknowledgement;
use App\Services\VoucherSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoucherDocumentRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_wide_transparent_stamp_and_signature_auto_fit_in_browser_print_and_pdf(): void
    {
        [$voucher, $acknowledgement, $applicant, $originals] = $this->completedDocument(2048, 930);
        $logoPath = public_path('images/logo-warga-cakna.png');
        $logoChecksum = hash_file('sha256', $logoPath);

        $this->actingAs($applicant)->get(route('vouchers.document', $voucher))
            ->assertOk()
            ->assertSeeText('PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU')
            ->assertSeeText('BAUCAR BAYARAN')
            ->assertSee('/images/logo-warga-cakna.png', false)
            ->assertSee('document-logo-image', false)
            ->assertSee('document-signature-image', false)
            ->assertSee('document-stamp-image', false)
            ->assertSee('max-width:220px;max-height:80px;object-fit:contain', false)
            ->assertSee('max-width:190px;max-height:115px;object-fit:contain', false);

        $voucherPdf = $this->actingAs($applicant)->get(route('vouchers.pdf', $voucher))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $voucherPdf->getContent());

        $this->actingAs($applicant)->get(route('vouchers.acknowledgements.document', $acknowledgement))
            ->assertOk()
            ->assertSeeText('SURAT AKUAN PENERIMAAN BAYARAN')
            ->assertSeeText('PERSATUAN PRIHATIN WARGA TERPINGGIR TERENGGANU')
            ->assertSee('/images/logo-warga-cakna.png', false)
            ->assertSee('document-logo-image', false)
            ->assertSee('document-signature-image', false)
            ->assertSee('document-stamp-image', false)
            ->assertSee('object-fit: contain', false)
            ->assertSeeText('Cetak');

        $acknowledgementPdf = $this->actingAs($applicant)->get(route('vouchers.acknowledgements.pdf', $acknowledgement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $acknowledgementPdf->getContent());

        foreach ($originals as $path => $contents) {
            $this->assertSame($contents, Storage::disk('local')->get($path));
        }
        $this->assertSame($logoChecksum, hash_file('sha256', $logoPath));
    }

    public function test_square_and_portrait_stamps_render_in_browser_and_dompdf(): void
    {
        foreach ([[900, 900], [600, 1000]] as [$width, $height]) {
            [$voucher, $acknowledgement, $applicant] = $this->completedDocument($width, $height);

            $this->actingAs($applicant)->get(route('vouchers.document', $voucher))
                ->assertOk()
                ->assertSee('document-stamp-image', false);
            $this->actingAs($applicant)->get(route('vouchers.pdf', $voucher))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->actingAs($applicant)->get(route('vouchers.acknowledgements.document', $acknowledgement))
                ->assertOk()
                ->assertSee('document-stamp-image', false);
            $this->actingAs($applicant)->get(route('vouchers.acknowledgements.pdf', $acknowledgement))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    private function completedDocument(int $stampWidth, int $stampHeight): array
    {
        $applicant = User::factory()->create([
            'role' => 'ajk',
            'salutation' => 'Dr',
            'organization_position' => 'AJK Kebajikan',
        ]);
        $approver = User::factory()->create([
            'role' => 'admin',
            'salutation' => 'Tiada',
            'organization_position' => 'Pengerusi',
        ]);
        $voucher = PaymentVoucher::query()->create([
            'voucher_no' => 'B-2026-1028-'.str_pad((string) (PaymentVoucher::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'voucher_date' => '2026-10-28',
            'status' => PaymentVoucher::COMPLETED,
            'applicant_id' => $applicant->id,
            'approver_id' => $approver->id,
            'recipient_name' => 'Penerima Render UAT',
            'recipient_phone' => '0191234567',
            'recipient_email' => 'render@example.test',
            'purpose' => 'Ujian auto-fit dokumen',
            'amount' => '250.00',
            'paid_amount' => '250.00',
            'transaction_date' => '2026-10-28',
            'transaction_reference' => 'BANK-RENDER-UAT',
            'submitted_at' => now()->subHours(4),
            'approved_at' => now()->subHours(3),
            'issued_at' => now()->subHours(2),
            'payment_confirmed_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $applicantSignature = $this->transparentPng(1800, 400);
        $approverSignature = $this->transparentPng(1400, 350);
        $stamp = $this->transparentPng($stampWidth, $stampHeight);
        $originals = [
            "users/{$applicant->id}/signatures/original.png" => $applicantSignature,
            "users/{$approver->id}/signatures/original.png" => $approverSignature,
            'organization/stamps/original.png' => $stamp,
        ];

        foreach ($originals as $path => $contents) {
            Storage::disk('local')->put($path, $contents);
        }

        $applicant->update([
            'digital_signature_path' => "users/{$applicant->id}/signatures/original.png",
            'digital_signature_checksum' => hash('sha256', $applicantSignature),
            'digital_signature_uploaded_at' => now(),
        ]);
        $approver->update([
            'digital_signature_path' => "users/{$approver->id}/signatures/original.png",
            'digital_signature_checksum' => hash('sha256', $approverSignature),
            'digital_signature_uploaded_at' => now(),
        ]);
        Setting::query()->updateOrCreate(
            ['key' => 'organization_official_stamp_path'],
            ['value' => 'organization/stamps/original.png'],
        );

        $snapshots = app(VoucherSnapshotService::class);
        $applicantCopy = $snapshots->copyUserSignature($applicant, $voucher, 'applicant');
        $approverCopy = $snapshots->copyUserSignature($approver, $voucher, 'approver');
        $stampCopy = $snapshots->copyOfficialStamp($voucher);

        $voucher->update([
            'applicant_signature_snapshot_path' => $applicantCopy['path'],
            'applicant_signature_snapshot_checksum' => $applicantCopy['checksum'],
            'approver_signature_snapshot_path' => $approverCopy['path'],
            'approver_signature_snapshot_checksum' => $approverCopy['checksum'],
            'stamp_snapshot_path' => $stampCopy['path'],
            'stamp_snapshot_checksum' => $stampCopy['checksum'],
            'document_snapshot' => [
                'voucher_no' => $voucher->voucher_no,
                'voucher_date' => '2026-10-28',
                'applicant_name' => $applicant->name,
                'approver_name' => $approver->name,
                'applicant_salutation' => $applicant->salutation,
                'applicant_full_name' => $applicant->name,
                'applicant_organization_position' => $applicant->organization_position,
                'approver_salutation' => $approver->salutation,
                'approver_full_name' => $approver->name,
                'approver_organization_position' => $approver->organization_position,
                'recipient_name' => $voucher->recipient_name,
                'purpose' => $voucher->purpose,
                'amount' => '250.00',
                'issued_at' => $voucher->issued_at->toIso8601String(),
            ],
        ]);

        $this->assertSame($applicantSignature, Storage::disk('local')->get($applicantCopy['path']));
        $this->assertSame($approverSignature, Storage::disk('local')->get($approverCopy['path']));
        $this->assertSame($stamp, Storage::disk('local')->get($stampCopy['path']));

        $acknowledgement = VoucherAcknowledgement::query()->create([
            'payment_voucher_id' => $voucher->id,
            'token_hash' => hash('sha256', $voucher->voucher_no.'-render'),
            'expires_at' => now()->addDay(),
            'used_at' => now(),
            'recipient_name' => $voucher->recipient_name,
        ]);

        return [$voucher->fresh(), $acknowledgement, $applicant, $originals];
    }

    private function transparentPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        $ink = imagecolorallocatealpha($image, 10, 100, 55, 10);
        imagerectangle($image, 8, 8, $width - 9, $height - 9, $ink);
        imageline($image, 15, (int) ($height * 0.7), $width - 15, (int) ($height * 0.3), $ink);

        ob_start();
        imagepng($image, null, 6);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
