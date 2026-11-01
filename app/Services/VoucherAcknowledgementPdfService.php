<?php

namespace App\Services;

use App\Models\VoucherAcknowledgement;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use RuntimeException;

class VoucherAcknowledgementPdfService
{
    public function __construct(
        private readonly VoucherSnapshotService $snapshots,
        private readonly OrganizationBrandingService $branding,
    ) {}

    public function make(VoucherAcknowledgement $acknowledgement): PdfDocument
    {
        if ($acknowledgement->used_at === null) {
            throw new RuntimeException('Surat akuan hanya tersedia selepas penerima membuat akuan.');
        }

        $acknowledgement->loadMissing(['voucher.applicant', 'voucher.approver']);
        $voucher = $acknowledgement->voucher;
        if (! $voucher) {
            throw new RuntimeException('Rekod baucar bagi surat akuan tidak tersedia.');
        }

        return Pdf::loadView('pdf.voucher-acknowledgement', [
            'voucher' => $voucher,
            'acknowledgement' => $acknowledgement,
            'images' => $this->snapshots->documentImages($voucher),
            'branding' => $this->branding->documentBranding(),
        ])->setPaper('a4');
    }

    public function render(VoucherAcknowledgement $acknowledgement): string
    {
        return $this->make($acknowledgement)->output();
    }

    public function filename(VoucherAcknowledgement $acknowledgement): string
    {
        return "SURAT-AKUAN-PENERIMAAN-BAYARAN-{$acknowledgement->acknowledgement_no}.pdf";
    }
}
