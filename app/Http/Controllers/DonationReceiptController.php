<?php

namespace App\Http\Controllers;

use App\Models\DonationReceipt;
use App\Mail\DonationReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class DonationReceiptController extends Controller
{
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
            'donor_name'     => 'required|string|max:255',
            'donor_email'    => 'nullable|email|max:255',
            'donor_phone'    => 'nullable|string|max:50',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'description'    => 'nullable|string',
            'status'         => 'nullable|string|in:completed,pending,failed',
        ]);

        $validated['receipt_no'] = 'REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $validated['donated_at'] = now();
        $validated['status']     = $validated['status'] ?? 'completed';

        $receipt = DonationReceipt::create($validated);

        if ($receipt->donor_email && $receipt->status === 'completed') {
            try {
                Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            } catch (\Exception $e) {
                // Teruskan proses
            }
        }

        return redirect()->route('receipts.index')->with('success', "Resit {$receipt->receipt_no} berjaya dicipta!");
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
            'donor_name'     => 'required|string|max:255',
            'donor_email'    => 'nullable|email|max:255',
            'donor_phone'    => 'nullable|string|max:50',
            'amount'         => 'required|numeric|min:1',
            'status'         => 'required|in:completed,pending,failed',
            'payment_method' => 'required|string',
            'description'    => 'nullable|string',
        ]);

        $oldStatus = $receipt->status;

        $receipt->fill($validated);
        $receipt->save();

        // Hantar emel jika status bertukar kepada completed
        if ($oldStatus !== 'completed' && $receipt->status === 'completed' && $receipt->donor_email) {
            try {
                Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            } catch (\Exception $e) {
                // Abaikan jika emel gagal
            }
        }

        return redirect()->route('receipts.index')->with('success', "Rekod resit {$receipt->receipt_no} berjaya dikemaskini.");
    }

    /**
     * Pengesahan manual status bayaran oleh Admin (Butang Sahkan).
     */
    public function verify($id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        $receipt->status = 'completed';
        $receipt->save();

        if ($receipt->donor_email) {
            try {
                Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            } catch (\Exception $e) {
                return redirect()->route('receipts.index')->with('success', 'Status disahkan kepada BERJAYA (Gagal hantar emel).');
            }
        }

        return redirect()->route('receipts.index')->with('success', 'Bayaran berjaya disahkan dan emel resit telah dihantar!');
    }

    /**
     * Hantar semula emel resit.
     */
    public function resendEmail($id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        if (!$receipt->donor_email) {
            return redirect()->back()->with('error', 'Penderma ini tiada alamat emel.');
        }

        try {
            Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            return redirect()->back()->with('success', "Emel resit berjaya dihantar ke {$receipt->donor_email}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Gagal menghantar emel: " . $e->getMessage());
        }
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