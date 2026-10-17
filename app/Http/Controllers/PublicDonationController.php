<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Mail\DonationReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PublicDonationController extends Controller
{
    /**
     * Paparan Muka Depan
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
     * Paparan Butiran Kempen Sumbangan
     */
    public function show($slug)
    {
        $campaign = Campaign::where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        return view('public.show', compact('campaign'));
    }

    /**
     * Proses Hantar Sumbangan & Penjanaan Bil ToyyibPay
     */
    public function processDonation(Request $request)
    {
        $validated = $request->validate([
            'campaign_id'    => 'required',
            'donor_name'     => 'required|string|max:255',
            'donor_email'    => 'nullable|email|max:255',
            'donor_phone'    => 'required|string|max:50',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:toyyibpay,qr_pay,bank_transfer,cash',
            'description'    => 'nullable|string|max:255',
        ]);

        $receiptNo = 'REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // 1. Cipta Rekod Resit/Sumbangan (Status Pending)
        $receipt = DonationReceipt::create([
            'receipt_no'     => $receiptNo,
            'donor_name'     => $validated['donor_name'],
            'donor_email'    => $validated['donor_email'],
            'donor_phone'    => $validated['donor_phone'],
            'amount'         => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'description'    => $validated['description'] ?? 'Sumbangan Tabung Kebajikan WARGA CAKNA',
            'status'         => ($validated['payment_method'] === 'toyyibpay') ? 'pending' : 'pending',
            'donated_at'     => now(),
        ]);

        // 2. Jika Kaedah Bayaran ToyyibPay -> Panggil API ToyyibPay
        if ($validated['payment_method'] === 'toyyibpay') {
            $secretKey = env('TOYYIBPAY_SECRET_KEY', 'sandbox-secret-key');
            $categoryCode = env('TOYYIBPAY_CATEGORY_CODE', 'sandbox-category');
            $isDev = env('TOYYIBPAY_SANDBOX', true);

            $baseUrl = $isDev ? 'https://dev.toyyibpay.com' : 'https://toyyibpay.com';

            try {
                $response = Http::asForm()->post($baseUrl . '/index.php/api/createBill', [
                    'userSecretKey'            => $secretKey,
                    'categoryCode'             => $categoryCode,
                    'billName'                 => 'Sumbangan WARGA CAKNA',
                    'billDescription'          => substr($receipt->description, 0, 100),
                    'billPriceSetting'         => 1,
                    'billPayorInfo'            => 1,
                    'billAmount'               => $validated['amount'] * 100, // Amaun dalam sen
                    'billReturnUrl'            => route('payment.return'),
                    'billCallbackUrl'          => route('payment.callback'),
                    'billExternalReferenceNo'  => $receipt->receipt_no,
                    'billTo'                   => $validated['donor_name'],
                    'billEmail'                => $validated['donor_email'] ?? 'sumbang@wargacakna.org',
                    'billPhone'                => $validated['donor_phone'],
                    'billSplitPayment'         => 0,
                    'billPaymentChannel'       => '0',
                ]);

                $result = $response->json();

                if (isset($result[0]['BillCode'])) {
                    $billCode = $result[0]['BillCode'];
                    $receipt->update(['reference_no' => $billCode]);

                    // Redirect ke ToyyibPay Gateway
                    return redirect($baseUrl . '/' . $billCode);
                }
            } catch (\Exception $e) {
                // Log ralat jika gateway gagal
            }
        }

        // Jika Kaedah Manual (Tunai/QR/Bank Transfer)
        return redirect()->route('public.show', $request->campaign_id)
            ->with('success', 'Sumbangan anda telah direkodkan. Sila lakukan pemindahan dan urusetia kami akan mengesahkan resit anda.');
    }

    /**
     * ToyyibPay Return URL (Selepas Penderma Selesai Bayaran)
     */
    public function paymentReturn(Request $request)
    {
        $statusId = $request->query('status_id');
        $billCode = $request->query('billcode');
        $orderId  = $request->query('order_id');

        $receipt = DonationReceipt::where('reference_no', $billCode)
            ->orWhere('receipt_no', $orderId)
            ->first();

        if ($receipt && $statusId == '1') {
            if ($receipt->status !== 'completed') {
                $receipt->update(['status' => 'completed']);

                if ($receipt->donor_email) {
                    try {
                        Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
                    } catch (\Exception $e) {}
                }
            }

            return redirect()->route('receipts.show', $receipt->id)
                ->with('success', 'Pembayaran sumbangan anda telah BERJAYA! Terima kasih atas keprihatinan anda.');
        }

        return redirect()->route('public.index')
            ->with('error', 'Pembayaran tidak berjaya atau dibatalkan. Sila cuba semula.');
    }

    /**
     * ToyyibPay Direct Callback (Server-to-Server)
     */
    public function paymentCallback(Request $request)
    {
        $billCode = $request->input('billcode');
        $status   = $request->input('status');
        $orderId  = $request->input('order_id');

        $receipt = DonationReceipt::where('reference_no', $billCode)
            ->orWhere('receipt_no', $orderId)
            ->first();

        if ($receipt && $status == '1') {
            if ($receipt->status !== 'completed') {
                $receipt->update(['status' => 'completed']);

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