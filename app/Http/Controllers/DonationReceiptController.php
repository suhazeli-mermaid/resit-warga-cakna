<?php

namespace App\Http\Controllers;

use App\Models\DonationReceipt;
use App\Models\Donor;
use App\Models\DonationPurpose;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Setting;
use App\Mail\DonationReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class DonationReceiptController extends Controller
{
    public function index()
    {
        $currentUser = Auth::user();

        if ($currentUser->isAjk()) {
            $receipts = DonationReceipt::latest('id')->paginate(15);
            $users = User::latest()->get();
            $campaigns = Campaign::latest()->get();
        } else {
            $receipts = DonationReceipt::where('user_id', $currentUser->id)
                            ->latest('id')
                            ->paginate(15);
            $users = collect();
            $campaigns = collect();
        }

        $signaturePath = Setting::where('key', 'treasurer_signature')->value('value');

        foreach ($receipts as $receipt) {
            $st = strtolower($receipt->status ?? '');
            $pm = strtolower($receipt->payment_method ?? '');

            if ($st === 'paid' || $st === 'success' || ($st !== 'failed' && in_array($pm, ['fpx', 'cash', 'bank_transfer', 'qr_pay', 'cheque']))) {
                $receipt->is_paid_status = true;
            } else {
                $receipt->is_paid_status = false;
            }

            $phone = preg_replace('/[^0-9]/', '', $receipt->donor_phone ?? '');
            if (str_starts_with($phone, '0')) {
                $phone = '6' . $phone;
            }
            $receipt->clean_phone = $phone;

            $msg = "Salam sejahtera {$receipt->donor_name},\n\nTerima kasih atas niat murni anda untuk menyumbang RM " . number_format($receipt->amount, 2) . " bagi No. Resit {$receipt->receipt_no} di WARGA CAKNA.\n\nSekiranya anda menghadapi sebarang masalah transaksi, sila maklumkan kepada kami. Terima kasih!\n\nPersatuan Prihatin Warga Terpinggir Terengganu (WARGA CAKNA)";
            $receipt->wa_text = rawurlencode($msg);
        }

        return view('receipts.index', compact('receipts', 'users', 'campaigns', 'signaturePath'));
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
            'donor_email' => 'nullable|email|max:255',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:100',
            'donated_at' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'paid';

        Donor::updateOrCreate(
            ['name' => $validated['donor_name']],
            [
                'ic_passport' => $validated['donor_ic_passport'],
                'phone' => $validated['donor_phone'],
                'email' => $validated['donor_email'],
            ]
        );

        if (!empty($validated['description'])) {
            DonationPurpose::firstOrCreate([
                'title' => trim($validated['description'])
            ]);
        }

        $datePrefix = date('Y-md');
        $receiptPrefix = 'REC-' . $datePrefix . '-';
        $todayCount = DonationReceipt::where('receipt_no', 'LIKE', $receiptPrefix . '%')->count();
        $nextSeq = $todayCount + 1;
        $validated['receipt_no'] = $receiptPrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $receipt = DonationReceipt::create($validated);

        try {
            $currentUserEmail = Auth::user()->email;
            $mailable = Mail::to($currentUserEmail);

            if (!empty($receipt->donor_email) && $receipt->donor_email !== $currentUserEmail) {
                $mailable->cc($receipt->donor_email);
            }

            $mailable->send(new DonationReceiptMail($receipt));
            $msg = 'Resit derma berjaya dicipta & salinan emel telah dihantar!';
        } catch (\Exception $e) {
            $msg = 'Resit derma berjaya dicipta!';
        }

        return redirect()->route('receipts.index')->with('success', $msg);
    }

    // Muat naik Tandatangan Digital Bendahari
    public function uploadSignature(Request $request)
    {
        if (!Auth::user()->isAjk()) {
            return redirect()->back()->with('error', 'Hanya Admin/AJK dibenarkan memuat naik tandatangan!');
        }

        $request->validate([
            'signature' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $existing = Setting::where('key', 'treasurer_signature')->first();

        if ($existing && $existing->value && Storage::disk('public')->exists($existing->value)) {
            Storage::disk('public')->delete($existing->value);
        }

        $path = $request->file('signature')->store('signatures', 'public');

        Setting::updateOrCreate(
            ['key' => 'treasurer_signature'],
            ['value' => $path]
        );

        return redirect()->back()->with('success', 'Tandatangan digital Bendahari berjaya dikemaskini!');
    }

    public function sendReminderEmail($id)
    {
        if (!Auth::user()->isAjk()) {
            return redirect()->back()->with('error', 'Tindakan ini hanya untuk Admin/AJK!');
        }

        $receipt = DonationReceipt::findOrFail($id);

        if (empty($receipt->donor_email)) {
            return redirect()->back()->with('error', 'Tiada emel penderma direkodkan untuk resit ini.');
        }

        try {
            Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            return redirect()->back()->with('success', "Emel peringatan resit {$receipt->receipt_no} berjaya dihantar ke {$receipt->donor_email}!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghantar emel: ' . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        if (!Auth::user()->isAjk()) {
            return redirect()->back()->with('error', 'Tindakan ini hanya untuk Admin/AJK!');
        }

        $receipt = DonationReceipt::findOrFail($id);
        $receipt->status = 'paid';
        $receipt->save();

        if ($receipt->campaign) {
            $receipt->campaign->increment('collected_amount', $receipt->amount);
        }

        return redirect()->back()->with('success', "Status resit {$receipt->receipt_no} berjaya dikemaskini kepada BERJAYA!");
    }

    public function searchDonors(Request $request)
    {
        $query = $request->get('q');
        $donors = Donor::where('name', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->orWhere('ic_passport', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get();

        return response()->json($donors);
    }

    // Papar Resit PDF terus dalam Browser (stream)
    public function viewPdf($id)
    {
        $currentUser = Auth::user();

        if ($currentUser->isAjk()) {
            $receipt = DonationReceipt::findOrFail($id);
        } else {
            $receipt = DonationReceipt::where('id', $id)
                        ->where('user_id', $currentUser->id)
                        ->firstOrFail();
        }

        $signaturePath = Setting::where('key', 'treasurer_signature')->value('value');

        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt', 'signaturePath'))
                  ->setPaper('a4', 'portrait');

        return $pdf->stream($receipt->receipt_no . '.pdf');
    }

    // Muat Turun Resit PDF
    public function downloadPdf($id)
    {
        $currentUser = Auth::user();

        if ($currentUser->isAjk()) {
            $receipt = DonationReceipt::findOrFail($id);
        } else {
            $receipt = DonationReceipt::where('id', $id)
                        ->where('user_id', $currentUser->id)
                        ->firstOrFail();
        }

        $signaturePath = Setting::where('key', 'treasurer_signature')->value('value');

        $pdf = Pdf::loadView('pdf.donation-receipt', compact('receipt', 'signaturePath'))
                  ->setPaper('a4', 'portrait');

        return $pdf->download($receipt->receipt_no . '.pdf');
    }
}