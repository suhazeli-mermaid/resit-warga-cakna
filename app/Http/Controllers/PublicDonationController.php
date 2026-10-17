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
    /**
     * Paparan Senarai Kempen Utama
     */
    public function index()
    {
        $campaigns = Campaign::where('is_active', true)
            ->orWhereNull('is_active')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('public.index', compact('campaigns'));
    }

    /**
     * Paparan Butiran Kempen
     */
    public function show($slug)
    {
        $campaign = Campaign::where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        return view('public.show', compact('campaign'));
    }

    /**
     * Pemprosesan Sumbangan (ToyyibPay Direct Live & Bayaran Manual)
     */
    public function processDonation(Request $request)
    {
        $validated = $request->validate([
            'campaign_id'           => 'required',
            'donor_name'            => 'required|string|max:255',
            'donor_email'           => 'nullable|email|max:255',
            'donor_phone'           => 'required|string|max:50',
            'amount'                => 'required|numeric|min:1', // Menolak amaun RM 0
            'payment_method'        => 'required|string|in:toyyibpay,bank_transfer,qr_pay,cash',
            'transaction_reference' => 'nullable|string|max:255',
            'proof_file'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'description'           => 'nullable|string|max:255',
        ]);

        // Muat naik fail bukti bayaran (jika ada)
        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('proofs', 'public');
        }

        $receiptNo = 'REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // 1. Cipta Rekod Resit Sementara (Status Pending)
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

        // 2. PEMPROSESAN TOYYIBPAY DIRECT LIVE API
        if ($validated['payment_method'] === 'toyyibpay') {
            
            // Dapatkan nilai kunci ToyyibPay Live
$secretKey    = config('services.toyyibpay.secret_key') 
                ?: (env('TOYYIBPAY_SECRET_KEY') ?: 'kxmduu2g-7zzc-uysd-qry2-sk1fthtje5g8');

$categoryCode = config('services.toyyibpay.category_code') 
                ?: (env('TOYYIBPAY_CATEGORY_CODE') ?: 's12aui8d');

$baseUrl      = config('services.toyyibpay.base_url', 'https://toyyibpay.com');

            // Fallback: Jika nilai masih kosong, baca terus daripada fail .env secara manual
            if (empty($secretKey) && file_exists(base_path('.env'))) {
                $envContent = file_get_contents(base_path('.env'));
                if (preg_match('/TOYYIBPAY_SECRET_KEY=(.*)/', $envContent, $matches)) {
                    $secretKey = trim($matches[1], " '\"");
                }
                if (preg_match('/TOYYIBPAY_CATEGORY_CODE=(.*)/', $envContent, $matches2)) {
                    $categoryCode = trim($matches2[1], " '\"");
                }
            }

            // Semak jika nilai kekunci masih tiada
            if (empty($secretKey) || empty($categoryCode)) {
                Log::error('ToyyibPay Keys Missing in Environment', [
                    'secret_key_present' => !empty($secretKey),
                    'category_code_present' => !empty($categoryCode)
                ]);
                
                return redirect()->back()->with('error', 'Kunci API ToyyibPay tidak dijumpai. Sila semak tetapan .env anda.');
            }

            try {
                $payload = [
                    'userSecretKey'           => $secretKey,
                    'categoryCode'            => $categoryCode,
                    'billName'                => 'Sumbangan WARGA CAKNA',
                    'billDescription'         => substr($receipt->description, 0, 100),
                    'billPriceSetting'        => 1,
                    'billPayorInfo'           => 1,
                    'billAmount'              => intval(round($validated['amount'] * 100)), // Amaun dalam sen
                    'billReturnUrl'           => route('payment.return'),
                    'billCallbackUrl'         => route('payment.callback'),
                    'billExternalReferenceNo' => $receipt->receipt_no,
                    'billTo'                  => $validated['donor_name'],
                    'billEmail'               => $validated['donor_email'] ?? 'sumbang@wargacakna.org',
                    'billPhone'               => $validated['donor_phone'],
                    'billSplitPayment'        => 0,
                    'billPaymentChannel'      => '0',
                ];

                // Panggilan API menggunakan .withoutVerifying() bagi mengelakkan cURL error 60 pada XAMPP Localhost
                $response = Http::withoutVerifying()
                    ->asForm()
                    ->timeout(15)
                    ->post($baseUrl . '/index.php/api/createBill', $payload);

                if ($response->successful()) {
                    $result = $response->json();

                    if (is_array($result) && isset($result[0]['BillCode'])) {
                        $billCode = $result[0]['BillCode'];
                        $receipt->update(['reference_no' => $billCode]);

                        // Direct Redirect ke Gateway Pembayaran ToyyibPay Live
                        return redirect($baseUrl . '/' . $billCode);
                    }
                }

                Log::error('ToyyibPay Live API Error Response', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);

            } catch (\Exception $e) {
                Log::error('ToyyibPay Connection Exception: ' . $e->getMessage());
            }

            return redirect()->back()->with('error', 'Gagal membina bil bayaran ToyyibPay Direct. Sila semak sambungan atau pilih kaedah bayaran manual.');
        }

        // 3. PEMPROSESAN BAYARAN MANUAL (BANK TRANSFER / DUITNOW QR / TUNAI)
        return redirect()->route('public.show', $request->campaign_id)
            ->with('success', 'Sumbangan anda telah direkodkan. Urusetia kami akan mengesahkan transaksi anda untuk pengeluaran resit rasmi.');
    }

/**
     * ToyyibPay Return URL (Donor Browser Redirect)
     */
    public function paymentReturn(Request $request)
    {
        $statusId = $request->query('status_id');
        $billCode = $request->query('billcode');
        $orderId  = $request->query('order_id');

        // Semakan selamat bagi menyokong pelbagai nama lajur pangkalan data
        $receipt = DonationReceipt::where('receipt_no', $orderId)
            ->orWhere(function ($query) use ($billCode) {
                if ($billCode) {
                    $query->when(\Schema::hasColumn('donation_receipts', 'reference_no'), function ($q) use ($billCode) {
                        $q->where('reference_no', $billCode);
                    })
                    ->when(\Schema::hasColumn('donation_receipts', 'payment_reference'), function ($q) use ($billCode) {
                        $q->orWhere('payment_reference', $billCode);
                    })
                    ->when(\Schema::hasColumn('donation_receipts', 'toyyibpay_bill_code'), function ($q) use ($billCode) {
                        $q->orWhere('toyyibpay_bill_code', $billCode);
                    });
                }
            })
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
                ->with('success', 'Pembayaran sumbangan anda telah BERJAYA! Resit rasmi telah dikeluarkan.');
        }

        return redirect()->route('public.index')
            ->with('error', 'Pembayaran tidak berjaya atau telah dibatalkan.');
    }

    /**
     * ToyyibPay Server-to-Server Callback
     */
    public function paymentCallback(Request $request)
    {
        $billCode = $request->input('billcode');
        $status   = $request->input('status');
        $orderId  = $request->input('order_id');

        $receipt = DonationReceipt::where('receipt_no', $orderId)
            ->orWhere(function ($query) use ($billCode) {
                if ($billCode) {
                    $query->when(\Schema::hasColumn('donation_receipts', 'reference_no'), function ($q) use ($billCode) {
                        $q->where('reference_no', $billCode);
                    })
                    ->when(\Schema::hasColumn('donation_receipts', 'payment_reference'), function ($q) use ($billCode) {
                        $q->orWhere('payment_reference', $billCode);
                    })
                    ->when(\Schema::hasColumn('donation_receipts', 'toyyibpay_bill_code'), function ($q) use ($billCode) {
                        $q->orWhere('toyyibpay_bill_code', $billCode);
                    });
                }
            })
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