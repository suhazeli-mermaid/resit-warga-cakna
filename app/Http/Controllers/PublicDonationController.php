<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignDetail;
use App\Models\CampaignUpdate;
use App\Models\DonationReceipt;
use App\Services\PaymentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublicDonationController extends Controller
{
    public function __construct(
        private PaymentNotificationService $notifications,
    ) {}

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

        $detail = CampaignDetail::where(
            'campaign_id',
            $campaign->id
        )
            ->where('is_published', true)
            ->first();

        $updates = CampaignUpdate::where(
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
            'campaign_id' => 'required',
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'donor_phone' => 'required|string|max:50',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:toyyibpay,bank_transfer,qr_pay,cash',
            'transaction_reference' => 'required_unless:payment_method,toyyibpay|nullable|string|max:255',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'description' => 'nullable|string|max:255',
        ]);

        $campaign = Campaign::query()
            ->where('slug', $validated['campaign_id'])
            ->orWhere('id', $validated['campaign_id'])
            ->firstOrFail();

        if ($validated['payment_method'] === 'toyyibpay' && ! $this->toyyibPayIsConfigured()) {
            return back()->withInput()->with('error', 'Konfigurasi ToyyibPay belum lengkap. Sila pilih kaedah bayaran manual atau hubungi pentadbir.');
        }

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('proofs', 'public');
        }

        $receiptNo = 'REC-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

        $receipt = DonationReceipt::create([
            'campaign_id' => $campaign->id,
            'receipt_no' => $receiptNo,
            'donor_name' => $validated['donor_name'],
            'donor_email' => $validated['donor_email'],
            'donor_phone' => $validated['donor_phone'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['transaction_reference'] ?? null,
            'proof_file' => $proofPath,
            'description' => $validated['description'] ?? 'Sumbangan Kebajikan WARGA CAKNA',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => now(),
        ]);

        if ($validated['payment_method'] === 'toyyibpay') {
            $secretKey = (string) config('services.toyyibpay.secret_key');
            $categoryCode = (string) config('services.toyyibpay.category_code');
            $baseUrl = rtrim((string) config('services.toyyibpay.base_url'), '/');

            try {
                $payload = [
                    'userSecretKey' => $secretKey,
                    'categoryCode' => $categoryCode,
                    'billName' => 'Sumbangan WARGA CAKNA',
                    'billDescription' => substr($receipt->description, 0, 100),
                    'billPriceSetting' => 1,
                    'billPayorInfo' => 1,
                    'billAmount' => (int) round($validated['amount'] * 100),
                    'billReturnUrl' => route('payment.return'),
                    'billCallbackUrl' => route('payment.callback'),
                    'billExternalReferenceNo' => $receipt->receipt_no,
                    'billTo' => $validated['donor_name'],
                    'billEmail' => $validated['donor_email']
                        ?: config('services.notifications.admin_email')
                        ?: config('mail.from.address'),
                    'billPhone' => $validated['donor_phone'],
                    'billSplitPayment' => 0,
                    'billPaymentChannel' => '0',
                ];

                $response = Http::asForm()->timeout(15)->post($baseUrl.'/index.php/api/createBill', $payload);

                if ($response->successful()) {
                    $result = $response->json();

                    if (is_array($result) && isset($result[0]['BillCode']) && is_string($result[0]['BillCode'])) {
                        $billCode = $result[0]['BillCode'];
                        $receipt->update(['toyyibpay_bill_code' => $billCode]);

                        return redirect($baseUrl.'/'.$billCode);
                    }
                }

                Log::warning('ToyyibPay bill creation failed.', ['http_status' => $response->status()]);
            } catch (Throwable) {
                Log::warning('ToyyibPay bill creation connection failed.');
            }

            return redirect()->back()->with('error', 'Gagal membina bil bayaran ToyyibPay Direct. Sila semak sambungan atau pilih kaedah bayaran manual.');
        }

        $this->notifications->manualReceived($receipt->fresh());

        return redirect()->route('public.show', $campaign->id)
            ->with('success', 'Sumbangan anda telah direkodkan. Urusetia kami akan mengesahkan transaksi anda untuk pengeluaran resit rasmi.');
    }

    public function paymentReturn(Request $request)
    {
        $statusId = (string) $request->query('status_id', '');
        $billCode = (string) $request->query('billcode', '');
        $orderId = (string) $request->query('order_id', '');

        $receipt = DonationReceipt::query()
            ->where('receipt_no', $orderId)
            ->first();

        if (! $receipt || ! $this->receiptMatchesBillCode($receipt, $billCode)) {
            return redirect()->route('public.index')
                ->with('error', 'Maklumat transaksi ToyyibPay tidak sepadan.');
        }

        if ($receipt->isCompleted()) {
            return redirect()->route('public.index')
                ->with('success', 'Pembayaran sumbangan anda telah disahkan BERJAYA.');
        }

        if ($statusId === '1') {
            return redirect()->route('public.index')
                ->with('success', 'Maklum balas bayaran diterima dan sedang menunggu pengesahan selamat daripada ToyyibPay.');
        }

        return redirect()->route('public.index')
            ->with('error', 'Pembayaran masih belum disahkan atau telah dibatalkan.');
    }

    public function paymentCallback(Request $request)
    {
        $secretKey = (string) config('services.toyyibpay.secret_key');

        if ($secretKey === '') {
            return response('CONFIGURATION ERROR', 503);
        }

        $billCode = $request->input('billcode');
        $status = $request->input('status');
        $orderId = $request->input('order_id');
        $reference = $request->input('refno');
        $amount = $request->input('amount');
        $receivedHash = $request->input('hash');

        foreach ([$billCode, $status, $orderId, $reference, $amount, $receivedHash] as $value) {
            if (! is_scalar($value) || (string) $value === '') {
                return response('INVALID CALLBACK', 422);
            }
        }

        $billCode = (string) $billCode;
        $status = (string) $status;
        $orderId = (string) $orderId;
        $reference = (string) $reference;
        $receivedHash = strtolower((string) $receivedHash);
        $expectedHash = md5($secretKey.$status.$orderId.$reference.'ok');

        if (! hash_equals($expectedHash, $receivedHash)) {
            return response('INVALID SIGNATURE', 403);
        }

        $receipt = DonationReceipt::query()
            ->where('receipt_no', $orderId)
            ->first();

        if (! $receipt || ! $this->receiptMatchesBillCode($receipt, $billCode)) {
            return response('TRANSACTION NOT FOUND', 404);
        }

        if ((int) round(((float) $amount) * 100) !== (int) round(((float) $receipt->amount) * 100)) {
            return response('AMOUNT MISMATCH', 422);
        }

        if ($status === '1') {
            $completedReceipt = DB::transaction(function () use ($receipt, $reference) {
                $lockedReceipt = DonationReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

                if ($lockedReceipt->isCompleted()) {
                    return null;
                }

                $lockedReceipt->forceFill([
                    'status' => DonationReceipt::STATUS_COMPLETED,
                    'payment_reference' => $reference,
                    'verified_by' => null,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ])->save();

                return $lockedReceipt->fresh();
            });

            if ($completedReceipt) {
                $this->notifications->completed($completedReceipt);
            }

            return response('OK', 200);
        }

        if ($status === '3') {
            DB::transaction(function () use ($receipt, $reference) {
                $lockedReceipt = DonationReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

                if (! $lockedReceipt->isCompleted()) {
                    $lockedReceipt->forceFill([
                        'status' => DonationReceipt::STATUS_FAILED,
                        'payment_reference' => $reference,
                    ])->save();
                }
            });

            return response('OK', 200);
        }

        if ($status === '2') {
            return response('OK', 200);
        }

        return response('INVALID STATUS', 422);
    }

    private function toyyibPayIsConfigured(): bool
    {
        return filled(config('services.toyyibpay.secret_key'))
            && filled(config('services.toyyibpay.category_code'))
            && filled(config('services.toyyibpay.base_url'));
    }

    private function receiptMatchesBillCode(DonationReceipt $receipt, string $billCode): bool
    {
        $storedBillCode = $receipt->toyyibpay_bill_code;

        return $billCode !== ''
            && is_string($storedBillCode)
            && $storedBillCode !== ''
            && hash_equals($storedBillCode, $billCode);
    }
}
