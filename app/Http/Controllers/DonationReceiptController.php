<?php

namespace App\Http\Controllers;

use App\Exceptions\PotentialDuplicateFavouriteException;
use App\Models\DonationReceipt;
use App\Models\FavouriteContact;
use App\Services\DonationReceiptAuditService;
use App\Services\FavouriteContactService;
use App\Services\PaymentNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationReceiptController extends Controller
{
    public function __construct(
        private PaymentNotificationService $notifications,
        private DonationReceiptAuditService $audits,
        private FavouriteContactService $favouriteContacts,
    ) {}

    /**
     * Memaparkan senarai resit dengan carian & isihan.
     */
    public function index(Request $request)
    {
        $isAdmin = $request->user()?->isAdmin() === true;
        $filter = (string) $request->input('filter', 'all');
        $allowedFilters = ['all', 'pending', 'completed', 'rejected', 'failed', 'archived', 'deleted'];

        if (! in_array($filter, $allowedFilters, true) || (! $isAdmin && in_array($filter, ['archived', 'deleted'], true))) {
            $filter = 'all';
        }

        $filterCounts = [];
        if ($isAdmin) {
            $activeReceipts = DonationReceipt::query()->whereNull('archived_at');
            $filterCounts = [
                'all' => (clone $activeReceipts)->count(),
                'pending' => (clone $activeReceipts)->where('status', DonationReceipt::STATUS_PENDING)->count(),
                'completed' => (clone $activeReceipts)->where('status', DonationReceipt::STATUS_COMPLETED)->count(),
                'rejected' => (clone $activeReceipts)->where('status', DonationReceipt::STATUS_REJECTED)->count(),
                'failed' => (clone $activeReceipts)->where('status', DonationReceipt::STATUS_FAILED)->count(),
                'archived' => DonationReceipt::query()->whereNotNull('archived_at')->count(),
                'deleted' => DonationReceipt::onlyTrashed()->count(),
            ];
        }

        $query = $filter === 'deleted' && $isAdmin
            ? DonationReceipt::onlyTrashed()->with('latestAudit')
            : DonationReceipt::query()->with('latestAudit');

        if (! $isAdmin) {
            $query->where('user_id', $request->user()->id)->whereNull('archived_at');
        } elseif ($filter === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif ($filter !== 'deleted') {
            $query->whereNull('archived_at');

            if ($filter !== 'all') {
                $query->where('status', $filter);
            }
        }

        // Carian Kata Kunci
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                    ->orWhere('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%");
            });
        }

        // Isihan (Sorting)
        $sort = $request->input('sort', 'latest');
        switch ($sort) {
            case 'oldest':
                $query->orderByRaw('COALESCE(donated_at, created_at) ASC')->orderBy('id');
                break;
            case 'amount_high':
                $query->orderBy('amount', 'desc');
                break;
            case 'amount_low':
                $query->orderBy('amount', 'asc');
                break;
            case 'latest':
            default:
                // Pending online payments have no donation date until their
                // authoritative gateway callback. Use creation time meanwhile
                // so legitimate new gateway receipts are not buried at the end.
                $query->orderByRaw('COALESCE(donated_at, created_at) DESC')->orderBy('id', 'desc');
                break;
        }

        $receipts = $query->paginate(15)->withQueryString();

        return view('receipts.index', compact('receipts', 'filter', 'filterCounts', 'isAdmin'));
    }

    /**
     * Memaparkan borang cipta resit baharu.
     */
    public function create(Request $request)
    {
        $this->ensureCanCreateReceipt($request);

        return view('receipts.create');
    }

    /**
     * Menyimpan rekod resit baharu ke dalam pangkalan data.
     */
    public function store(Request $request)
    {
        $this->ensureCanCreateReceipt($request);

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_ic_passport' => 'nullable|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'donor_phone' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:bank_transfer,online_transfer,qr_pay,cash',
            'payment_reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'donated_at' => 'required|date|before_or_equal:today',
        ]);

        $favourite = $request->validate([
            'favourite_contact_id' => 'nullable|integer|exists:donors,id',
            'save_as_favourite' => 'nullable|boolean',
            'duplicate_contact_id' => 'nullable|integer|exists:donors,id',
            'confirm_favourite_duplicate' => 'sometimes|accepted',
        ]);
        if ($request->boolean('save_as_favourite')) {
            abort_unless($request->user()->canManageFavourites(), 403);
        }

        $validated['receipt_no'] = 'REC-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
        $validated['status'] = DonationReceipt::STATUS_PENDING;
        $validated['user_id'] = $request->user()->id;

        try {
            $receipt = DB::transaction(function () use ($validated, $favourite, $request) {
                $receipt = DonationReceipt::create($validated);
                $selected = null;

                if (filled($favourite['favourite_contact_id'] ?? null)) {
                    $selected = FavouriteContact::query()
                        ->active()
                        ->forUsage(FavouriteContact::USAGE_DONOR)
                        ->findOrFail($favourite['favourite_contact_id']);
                }

                if ($request->boolean('save_as_favourite')) {
                    $this->favouriteContacts->saveFromTransaction(
                        data: [
                            'name' => $receipt->donor_name,
                            'identity_document' => $receipt->donor_ic_passport,
                            'phone' => $receipt->donor_phone,
                            'email' => $receipt->donor_email,
                        ],
                        usage: FavouriteContact::USAGE_DONOR,
                        actor: $request->user(),
                        selectedId: $selected?->id,
                        confirmedDuplicateId: $request->boolean('confirm_favourite_duplicate')
                            ? (int) ($favourite['duplicate_contact_id'] ?? 0)
                            : null,
                    );
                } elseif ($selected) {
                    $this->favouriteContacts->markUsed($selected);
                }

                return $receipt;
            });
        } catch (PotentialDuplicateFavouriteException $exception) {
            return back()->withInput()->withErrors([
                'save_as_favourite' => 'Favourite yang mungkin sama telah ditemui. Semak rekod dan sahkan jika anda mahu mengemas kini rekod tersebut.',
            ])->with('favourite_duplicate_candidate', [
                'id' => $exception->contact->id,
                'name' => $exception->contact->name,
                'phone' => $exception->contact->phone,
                'email' => $exception->contact->email,
            ]);
        }

        $this->notifications->manualReceived($receipt);

        return redirect()->route('receipts.index')->with('success', "Transaksi {$receipt->receipt_no} direkodkan dan sedang menunggu pengesahan.");
    }

    /**
     * Memaparkan perincian resit tunggal.
     */
    public function show(Request $request, $id)
    {
        $receipt = DonationReceipt::withTrashed()->findOrFail($id);
        $this->ensureReceiptAccess($request, $receipt);

        return view('receipts.show', compact('receipt'));
    }

    /**
     * Borang kemaskini resit.
     */
    public function edit(Request $request, $id)
    {
        $this->ensureAdmin($request);
        $receipt = DonationReceipt::findOrFail($id);
        abort_unless($receipt->isPending() && ! $receipt->isArchived(), 409, 'Hanya transaksi pending boleh dikemas kini.');

        return view('receipts.edit', compact('receipt'));
    }

    /**
     * Simpan kemaskini resit.
     */
    public function update(Request $request, $id)
    {
        $this->ensureAdmin($request);
        $receipt = DonationReceipt::findOrFail($id);
        abort_unless($receipt->isPending() && ! $receipt->isArchived(), 409, 'Hanya transaksi pending boleh dikemas kini.');

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'donor_phone' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:1',
            'payment_reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'donated_at' => 'required|date|before_or_equal:today',
        ]);

        $receipt->fill($validated);
        $receipt->save();

        return redirect()->route('receipts.index')->with('success', "Rekod resit {$receipt->receipt_no} berjaya dikemaskini.");
    }

    /**
     * Pengesahan manual status bayaran oleh Admin (Butang Sahkan).
     */
    public function verify(Request $request, $id)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $result = DB::transaction(function () use ($id, $request) {
            $receipt = DonationReceipt::query()->lockForUpdate()->findOrFail($id);

            if ($receipt->isCompleted()) {
                return ['state' => 'already', 'receipt' => $receipt];
            }

            if (! $receipt->isManualPayment() || ! $receipt->isPending()) {
                return ['state' => 'invalid', 'receipt' => $receipt];
            }

            $receipt->forceFill([
                'status' => DonationReceipt::STATUS_COMPLETED,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->audits->record($receipt, 'verify', $request->user());

            return ['state' => 'completed', 'receipt' => $receipt->fresh()];
        });

        if ($result['state'] === 'completed') {
            $this->notifications->completed($result['receipt']);

            return redirect()->route('receipts.index')->with('success', 'Bayaran telah disahkan sebagai BERJAYA.');
        }

        if ($result['state'] === 'already') {
            return redirect()->route('receipts.index')->with('success', 'Bayaran ini telah disahkan sebelum ini; tiada e-mel berulang dihantar.');
        }

        return redirect()->route('receipts.index')->with('error', 'Hanya transaksi manual yang masih menunggu boleh disahkan oleh Admin.');
    }

    /**
     * Penolakan bayaran manual oleh Admin.
     */
    public function reject(Request $request, $id)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $result = DB::transaction(function () use ($id, $request, $validated) {
            $receipt = DonationReceipt::query()->lockForUpdate()->findOrFail($id);

            if ($receipt->status === DonationReceipt::STATUS_REJECTED) {
                return ['state' => 'already', 'receipt' => $receipt];
            }

            if (! $receipt->isManualPayment() || ! $receipt->isPending()) {
                return ['state' => 'invalid', 'receipt' => $receipt];
            }

            $receipt->forceFill([
                'status' => DonationReceipt::STATUS_REJECTED,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ])->save();

            $this->audits->record(
                $receipt,
                'reject',
                $request->user(),
                $validated['rejection_reason'],
            );

            return ['state' => 'rejected', 'receipt' => $receipt->fresh()];
        });

        if ($result['state'] === 'rejected') {
            $this->notifications->rejected($result['receipt']);

            return redirect()->route('receipts.index')->with('success', 'Transaksi telah ditolak dan sebab penolakan direkodkan.');
        }

        if ($result['state'] === 'already') {
            return redirect()->route('receipts.index')->with('success', 'Transaksi ini telah ditolak sebelum ini; tiada e-mel berulang dihantar.');
        }

        return redirect()->route('receipts.index')->with('error', 'Hanya transaksi manual yang masih menunggu boleh ditolak oleh Admin.');
    }

    /**
     * Hantar semula emel resit.
     */
    public function resendEmail(Request $request, $id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        $this->ensureReceiptAccess($request, $receipt);

        if (! $receipt->donor_email) {
            return redirect()->back()->with('error', 'Penderma ini tiada alamat emel.');
        }

        if (! $receipt->isCompleted()) {
            return redirect()->back()->with('error', 'Resit rasmi hanya boleh dihantar selepas bayaran disahkan BERJAYA.');
        }

        if ($this->notifications->resendReceipt($receipt, resentBy: $request->user())) {
            return redirect()->back()->with('success', "Emel resit berjaya dihantar ke {$receipt->donor_email}.");
        }

        return redirect()->back()->with('error', 'Emel tidak dihantar. Sila semak konfigurasi SMTP yang selamat.');
    }

    /**
     * Muat turun fail PDF resit.
     */
    public function downloadPdf(Request $request, $id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        $this->ensureReceiptAccess($request, $receipt);
        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt'));

        return $pdf->download("Resit-Sumbangan-{$receipt->receipt_no}.pdf");
    }

    /**
     * Papar PDF dalam pelayar web.
     */
    public function viewPdf(Request $request, $id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        $this->ensureReceiptAccess($request, $receipt);
        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt'));

        return $pdf->stream("Resit-Sumbangan-{$receipt->receipt_no}.pdf");
    }

    public function destroy(Request $request, $id)
    {
        $this->ensureAdmin($request);
        $validated = $request->validate([
            'deletion_reason' => 'required|string|max:2000',
        ]);

        $result = DB::transaction(function () use ($id, $request, $validated) {
            $receipt = DonationReceipt::query()->lockForUpdate()->findOrFail($id);

            if ($receipt->isCompleted()) {
                return ['state' => 'completed', 'receipt' => $receipt];
            }

            $this->audits->record(
                $receipt,
                'delete',
                $request->user(),
                $validated['deletion_reason'],
            );
            $receipt->delete();

            return ['state' => 'deleted', 'receipt' => $receipt];
        });

        if ($result['state'] === 'completed') {
            return redirect()->route('receipts.index')
                ->with('error', 'Transaksi BERJAYA tidak boleh dihapus. Gunakan tindakan Arkibkan.');
        }

        return redirect()->route('receipts.index')
            ->with('success', "Rekod {$result['receipt']->receipt_no} dipindahkan ke Tong Sampah.");
    }

    public function restore(Request $request, $id)
    {
        $this->ensureAdmin($request);

        $receipt = DB::transaction(function () use ($id, $request) {
            $receipt = DonationReceipt::onlyTrashed()->lockForUpdate()->findOrFail($id);
            $receipt->restore();
            $this->audits->record($receipt, 'restore', $request->user());

            return $receipt;
        });

        return redirect()->route('receipts.index', ['filter' => 'deleted'])
            ->with('success', "Rekod {$receipt->receipt_no} berjaya dipulihkan.");
    }

    public function archive(Request $request, $id)
    {
        $this->ensureAdmin($request);

        $result = DB::transaction(function () use ($id, $request) {
            $receipt = DonationReceipt::query()->lockForUpdate()->findOrFail($id);

            if ($receipt->isArchived()) {
                return ['state' => 'already', 'receipt' => $receipt];
            }

            if (! in_array($receipt->status, [
                DonationReceipt::STATUS_COMPLETED,
                DonationReceipt::STATUS_REJECTED,
                DonationReceipt::STATUS_FAILED,
            ], true)) {
                return ['state' => 'invalid', 'receipt' => $receipt];
            }

            $receipt->forceFill(['archived_at' => now()])->save();
            $this->audits->record($receipt, 'archive', $request->user());

            return ['state' => 'archived', 'receipt' => $receipt];
        });

        if ($result['state'] === 'invalid') {
            return redirect()->route('receipts.index')
                ->with('error', 'Transaksi pending tidak boleh diarkibkan. Sahkan, tolak atau hapus rekod tersebut.');
        }

        return redirect()->route('receipts.index')
            ->with('success', $result['state'] === 'already'
                ? 'Rekod telah diarkibkan sebelum ini.'
                : "Rekod {$result['receipt']->receipt_no} berjaya diarkibkan.");
    }

    public function unarchive(Request $request, $id)
    {
        $this->ensureAdmin($request);

        $receipt = DB::transaction(function () use ($id, $request) {
            $receipt = DonationReceipt::query()->lockForUpdate()->findOrFail($id);

            if ($receipt->isArchived()) {
                $receipt->forceFill(['archived_at' => null])->save();
                $this->audits->record($receipt, 'unarchive', $request->user());
            }

            return $receipt;
        });

        return redirect()->route('receipts.index', ['filter' => 'archived'])
            ->with('success', "Rekod {$receipt->receipt_no} dipulihkan daripada arkib.");
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function ensureCanCreateReceipt(Request $request): void
    {
        abort_unless($request->user()?->canCreateReceipt(), 403);
    }

    private function ensureReceiptAccess(Request $request, DonationReceipt $receipt): void
    {
        $user = $request->user();

        abort_unless(
            $user?->isAdmin()
                || (! $receipt->trashed()
                    && ! $receipt->isArchived()
                    && $receipt->user_id !== null
                    && $receipt->user_id === $user?->id),
            403,
        );
    }
}
