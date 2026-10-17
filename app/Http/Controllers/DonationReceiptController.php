<?php

namespace App\Http\Controllers;

use App\Models\DonationReceipt;
use App\Mail\DonationReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class DonationReceiptController extends Controller
{
    /**
     * Senarai Resit (Landing Page)
     */
    public function index(Request $request)
    {
        $query = DonationReceipt::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                  ->orWhere('donor_name', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $receipts = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('receipts.index', compact('receipts'));
    }

    /**
     * Paparan Butiran Resit
     */
    public function show($id)
    {
        $receipt = DonationReceipt::findOrFail($id);
        return view('receipts.show', compact('receipt'));
    }

    /**
     * Pengesahan Pentadbir (Approve / Reject Sumbangan Manual)
     */
    public function verify(Request $request, $id)
    {
        $receipt = DonationReceipt::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:completed,failed',
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        if ($validated['status'] === 'completed') {
            $receipt->update([
                'status' => 'completed',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);

            // Hantar emel resit digital jika emel wujud
            if ($receipt->donor_email) {
                try {
                    Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
                } catch (\Exception $e) {}
            }

            return redirect()->back()->with('success', 'Sumbangan telah DISAHKAN & LULUS. Resit rasmi telah dikeluarkan.');
        } else {
            $receipt->update([
                'status' => 'failed',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?? 'Pengesahan bayaran gagal.',
            ]);

            return redirect()->back()->with('error', 'Sumbangan telah DITOLAK.');
        }
    }
}