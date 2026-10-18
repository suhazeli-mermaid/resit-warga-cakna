<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Mail\DonationReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PublicDonationController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::where('is_active', true)
            ->orWhereNull('is_active')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('public.index', compact('campaigns'));
    }

    public function show($slug)
    {
        $campaign = Campaign::where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        return view('public.show', compact('campaign'));
    }

    /**
     * Halaman Maklumat Lanjut Kempen
     * Profil, Timeline, Galeri dan YouTube.
     */
    public function information($slug)
    {
        $campaign = Campaign::where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        abort_unless($campaign->is_active !== false, 404);

        $detail = \App\Models\CampaignDetail::where(
            'campaign_id',
            $campaign->id
        )
            ->where('is_published', true)
            ->first();

        $updates = \App\Models\CampaignUpdate::where(
            'campaign_id',
            $campaign->id
        )
            ->where('is_published', true)
            ->with(['media' => function ($query) {
                $query->where('is_published', true)
                    ->orderBy('sort_order');
            }])
            ->orderByDesc('activity_date')
            ->get();

        return view(
            'public.campaign-information',
            compact('campaign', 'detail', 'updates')
        );
    }

    public function processDonation(Request $request)
    {
        $validated = $request->validate([
            'campaign_id'           => 'required',
            'donor_name'            => 'required|string|max:255',
            'donor_email'           => 'nullable|email|max:255',
            'donor_phone'           => 'required|string|max:50',
            'amount'                => 'required|numeric|min:1',
            'payment_method'        => 'required|string|in:toyyibpay,bank_transfer,qr_pay,cash',
            'transaction_reference' => 'nullable|string|max:255',
            'proof_file'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'description'           => 'nullable|string|max:255',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('proofs', 'public');
        }

        $receiptNo = 'REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $receipt = DonationReceipt::create([
            'receipt_no'     => $receiptNo,
            'donor_name'     => $validated['donor_name'],
            'donor_email'    => $validated['donor_email'],
            'donor_phone'    => $validated['donor_phone'],
            'amount'         => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'reference_no'   => $validated['transaction_reference'] ?? null,
            'proof_file'     => $proofPath,
            'description'    => $validated['description'] ?? 'Sumbangan Kebajikan WARGA CAKNA',
            'status'         => 'pending',
            'donated_at'     => now(),
        ]);

        if ($validated['payment_method'] === 'toyyibpay') {
            $secretKey    = config('services.toyyibpay.secret_key') ?: (env('TOYYIBPAY_SECRET_KEY') ?: 'kxmduu2g-7zzc-uysd-qry2-sk1fthtje5g8');
            $categoryCode = config('services.toyyibpay.category_code') ?: (env('TOYYIBPAY_CATEGORY_CODE') ?: 's12aui8d');
            $baseUrl      = config('services.toyyibpay.base_url', 'https://toyyibpay.com');

            try {
                $payload = [
                    'userSecretKey'           => $secretKey,
                    'categoryCode'            => $categoryCode,
                    'billName'                => 'Sumbangan WARGA CAKNA',
                    'billDescription'         => substr($receipt->description, 0, 100),
                    'billPriceSetting'        => 1,
                    'billPayorInfo'           => 1,
                    'billAmount'              => intval(round($validated['amount'] * 100)),
                    'billReturnUrl'           => route('payment.return'),
                    'billCallbackUrl'         => route('payment.callback'),
                    'billExternalReferenceNo' => $receipt->receipt_no,
                    'billTo'                  => $validated['donor_name'],
                    'billEmail'               => $validated['donor_email'] ?? 'sumbang@wargacakna.org',
                    'billPhone'               => $validated['donor_phone'],
                    'billSplitPayment'        => 0,
                    'billPaymentChannel'      => '0',
                ];

                $response = Http::withoutVerifying()->asForm()->timeout(15)->post($baseUrl . '/index.php/api/createBill', $payload);

                if ($response->successful()) {
                    $result = $response->json();

                    if (is_array($result) && isset($result[0]['BillCode'])) {
                        $billCode = $result[0]['BillCode'];
                        $receipt->update(['reference_no' => $billCode]);

                        return redirect($baseUrl . '/' . $billCode);
                    }
                }

                Log::error('ToyyibPay Error Response', ['body' => $response->body()]);
            } catch (\Exception $e) {
                Log::error('ToyyibPay Exception: ' . $e->getMessage());
            }

            return redirect()->back()->with('error', 'Gagal membina bil bayaran ToyyibPay Direct. Sila semak sambungan atau pilih kaedah bayaran manual.');
        }

        return redirect()->route('public.show', $request->campaign_id)
            ->with('success', 'Sumbangan anda telah direkodkan. Urusetia kami akan mengesahkan transaksi anda untuk pengeluaran resit rasmi.');
    }

    public function paymentReturn(Request $request)
    {
        $statusId = $request->query('status_id');
        $billCode = $request->query('billcode');
        $orderId  = $request->query('order_id');

        $receipt = DonationReceipt::where('receipt_no', $orderId)
            ->orWhere('reference_no', $billCode)
            ->first();

        if ($receipt && $statusId == '1') {
            if ($receipt->status !== 'completed') {
                $receipt->update([
                    'status' => 'completed',
                    'verified_at' => now(),
                ]);

                if ($receipt->donor_email) {
                    try {
                        Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
                    } catch (\Exception $e) {}
                }
            }

            return redirect()->route('receipts.show', $receipt->id)
                ->with('success', 'Pembayaran sumbangan anda telah BERJAYA! Resit rasmi telah dikeluarkan secara automatik.');
        }

        return redirect()->route('public.index')
            ->with('error', 'Pembayaran tidak berjaya atau telah dibatalkan.');
    }

    public function paymentCallback(Request $request)
    {
        $billCode = $request->input('billcode');
        $status   = $request->input('status');
        $orderId  = $request->input('order_id');

        $receipt = DonationReceipt::where('receipt_no', $orderId)
            ->orWhere('reference_no', $billCode)
            ->first();

        if ($receipt && $status == '1') {
            if ($receipt->status !== 'completed') {
                $receipt->update([
                    'status' => 'completed',
                    'verified_at' => now(),
                ]);

                if ($receipt->donor_email) {
                    try {
                        Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
                    } catch (\Exception $e) {}
                }
            }
            return response('OK', 200);
        }

        return response('FAILED', 400);
    }
}