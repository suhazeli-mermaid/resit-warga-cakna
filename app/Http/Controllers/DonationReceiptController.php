<?php

namespace App\Http\Controllers;

use App\Models\DonationReceipt;
use App\Services\PaymentNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationReceiptController extends Controller
{
    public function __construct(
        private PaymentNotificationService $notifications,
    ) {}

    /**
     * Memaparkan senarai resit dengan carian & isihan.
     */
    public function index(Request $request)
    {
        $query = DonationReceipt::query();

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

        // Tapis mengikut Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Isihan (Sorting)
        $sort = $request->input('sort', 'latest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('donated_at', 'asc');
                break;
            case 'amount_high':
                $query->orderBy('amount', 'desc');
                break;
            case 'amount_low':
                $query->orderBy('amount', 'asc');
                break;
            case 'latest':
            default:
                $query->orderBy('donated_at', 'desc')->orderBy('id', 'desc');
                break;
        }

        $receipts = $query->paginate(15)->withQueryString();

        return view('receipts.index', compact('receipts'));
    }

    /**
     * Memaparkan borang cipta resit baharu.
     */
    public function create()
    {
        return view('receipts.create');
    }

    /**
     * Menyimpan rekod resit baharu ke dalam pangkalan data.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_ic_passport' => 'nullable|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'donor_phone' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:bank_transfer,online_transfer,qr_pay,cash,fpx,cheque',
            'payment_reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'donated_at' => 'nullable|date',
        ]);

        $validated['receipt_no'] = 'REC-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
        $validated['donated_at'] = $request->filled('donated_at') ? $request->donated_at : now();
        $validated['status'] = DonationReceipt::STATUS_PENDING;
        $validated['user_id'] = $request->user()->id;

        $receipt = DonationReceipt::create($validated);

        $this->notifications->manualReceived($receipt);

        return redirect()->route('receipts.index')->with('success', "Transaksi {$receipt->receipt_no} direkodkan dan sedang menunggu pengesahan.");
    }

    /**
     * Memaparkan perincian resit tunggal.
     */
    public function show($id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        return view('receipts.show', compact('receipt'));
    }

    /**
     * Borang kemaskini resit.
     */
    public function edit($id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        return view('receipts.edit', compact('receipt'));
    }

    /**
     * Simpan kemaskini resit.
     */
    public function update(Request $request, $id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'donor_phone' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:1',
            'payment_reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'donated_at' => 'nullable|date',
        ]);

        // Jika tarikh dihantar dalam borang, kemaskini tarikh tersebut
        if ($request->filled('donated_at')) {
            $validated['donated_at'] = $request->donated_at;
        }

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
    public function resendEmail($id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        if (! $receipt->donor_email) {
            return redirect()->back()->with('error', 'Penderma ini tiada alamat emel.');
        }

        if (! $receipt->isCompleted()) {
            return redirect()->back()->with('error', 'Resit rasmi hanya boleh dihantar selepas bayaran disahkan BERJAYA.');
        }

        if ($this->notifications->resendReceipt($receipt)) {
            return redirect()->back()->with('success', "Emel resit berjaya dihantar ke {$receipt->donor_email}.");
        }

        return redirect()->back()->with('error', 'Emel tidak dihantar. Sila semak konfigurasi SMTP yang selamat.');
    }

    /**
     * Muat turun fail PDF resit.
     */
    public function downloadPdf($id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt'));

        return $pdf->download("Resit-Sumbangan-{$receipt->receipt_no}.pdf");
    }

    /**
     * Papar PDF dalam pelayar web.
     */
    public function viewPdf($id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt'));

        return $pdf->stream("Resit-Sumbangan-{$receipt->receipt_no}.pdf");
    }
}
