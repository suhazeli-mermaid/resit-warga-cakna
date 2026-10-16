<?php

namespace App\Http\Controllers;

use App\Models\DonationReceipt;
use App\Models\Donor;
use App\Models\DonationPurpose;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class DonationReceiptController extends Controller
{
    public function index()
    {
        $receipts = DonationReceipt::latest()->paginate(10);
        return view('receipts.index', compact('receipts'));
    }

    public function create()
    {
        $purposes = DonationPurpose::orderBy('title', 'asc')->get();
        return view('receipts.create', compact('purposes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_ic_passport' => 'nullable|string|max:50',
            'donor_phone' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:100',
            'donated_at' => 'required|date',
            'description' => 'nullable|string',
        ]);

        // Auto-save penderma
        Donor::updateOrCreate(
            ['name' => $validated['donor_name']],
            [
                'ic_passport' => $validated['donor_ic_passport'],
                'phone' => $validated['donor_phone'],
            ]
        );

        // Auto-save tujuan sumbangan
        if (!empty($validated['description'])) {
            DonationPurpose::firstOrCreate([
                'title' => trim($validated['description'])
            ]);
        }

        // Jana No. Resit automatik
        $latest = DonationReceipt::latest()->first();
        $nextNumber = $latest ? ((int) filter_var($latest->receipt_no, FILTER_SANITIZE_NUMBER_INT)) + 1 : 1;
        $validated['receipt_no'] = 'REC-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        DonationReceipt::create($validated);

        return redirect()->route('receipts.index')->with('success', 'Resit derma berjaya dicipta!');
    }

    public function searchDonors(Request $request)
    {
        $query = $request->get('q');
        $donors = Donor::where('name', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->orWhere('ic_passport', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get();

        return response()->json($donors);
    }

    public function downloadPdf($id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt'))
                  ->setPaper('a4', 'portrait');

        return $pdf->download($receipt->receipt_no . '.pdf');
    }
}