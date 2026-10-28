<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoucherAcknowledgement;
use App\Services\OrganizationBrandingService;
use App\Services\VoucherSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class VoucherAcknowledgementController extends Controller
{
    public function __construct(
        private readonly VoucherSnapshotService $snapshots,
        private readonly OrganizationBrandingService $branding,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', VoucherAcknowledgement::class);
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['pending', 'completed', 'expired'])],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = VoucherAcknowledgement::query()
            ->with(['voucher.applicant', 'voucher.latestAcknowledgementSentAudit'])
            ->whereHas('voucher', fn (Builder $voucher) => $voucher->visibleTo($request->user()));

        if (filled($filters['search'] ?? null)) {
            $value = $filters['search'];
            $voucherValue = str_starts_with(strtoupper($value), 'A-') ? 'B-'.substr($value, 2) : $value;
            $term = '%'.addcslashes($voucherValue, '%_\\').'%';
            $query->whereHas('voucher', function (Builder $voucher) use ($term) {
                $voucher->where(function (Builder $search) use ($term) {
                    $search->where('voucher_no', 'like', $term)
                        ->orWhere('recipient_name', 'like', $term);
                });
            });
        }

        match ($filters['status'] ?? null) {
            'pending' => $query->whereNull('used_at')->where('expires_at', '>', now()),
            'completed' => $query->whereNotNull('used_at'),
            'expired' => $query->whereNull('used_at')->where('expires_at', '<=', now()),
            default => null,
        };

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return view('vouchers.acknowledgements.index', [
            'acknowledgements' => $query->latest('created_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function show(VoucherAcknowledgement $acknowledgement)
    {
        Gate::authorize('view', $acknowledgement);
        $acknowledgement->load([
            'voucher.applicant',
            'voucher.paymentConfirmer',
            'voucher.latestAcknowledgementSentAudit',
            'voucher.audits.actor',
        ]);

        return view('vouchers.acknowledgements.show', compact('acknowledgement'));
    }

    public function document(VoucherAcknowledgement $acknowledgement)
    {
        Gate::authorize('view', $acknowledgement);
        abort_unless($acknowledgement->used_at !== null, 409, 'Surat akuan hanya tersedia selepas penerima membuat akuan.');
        $acknowledgement->load('voucher.applicant', 'voucher.approver');

        return view('vouchers.acknowledgements.document', [
            'voucher' => $acknowledgement->voucher,
            'acknowledgement' => $acknowledgement,
            'images' => $this->snapshots->documentImages($acknowledgement->voucher),
            'branding' => $this->branding->documentBranding(),
        ]);
    }

    public function pdf(VoucherAcknowledgement $acknowledgement)
    {
        Gate::authorize('view', $acknowledgement);
        abort_unless($acknowledgement->used_at !== null, 409, 'Surat akuan hanya tersedia selepas penerima membuat akuan.');
        $acknowledgement->load('voucher');

        return Pdf::loadView('pdf.voucher-acknowledgement', [
            'voucher' => $acknowledgement->voucher,
            'acknowledgement' => $acknowledgement,
            'images' => $this->snapshots->documentImages($acknowledgement->voucher),
            'branding' => $this->branding->documentBranding(),
        ])->setPaper('a4')->download("Akuan-{$acknowledgement->acknowledgement_no}.pdf");
    }
}
