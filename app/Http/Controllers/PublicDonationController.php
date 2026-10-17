<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Models\User;
use App\Models\Donor;
use App\Mail\DonationReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicDonationController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::where('is_active', 1)
                        ->orWhereNull('is_active')
                        ->latest()
                        ->get();

        return view('public.index', compact('campaigns'));
    }

    public function show($slug)
    {
        $campaign = Campaign::where('slug', $slug)->firstOrFail();
        return view('public.show', compact('campaign'));
    }

    public function storeCampaign(Request $request)
    {
        if (!Auth::user()->isAjk()) {
            return redirect()->back()->with('error', 'Anda tidak mempunyai kebenaran untuk melakukan tindakan ini!');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . rand(100, 999);
        $validated['collected_amount'] = 0;
        $validated['target_amount'] = $validated['target_amount'] ?? 0;
        $validated['is_active'] = 1;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('campaigns', 'public');
            $validated['image_path'] = $path;
        }

        Campaign::create($validated);

        return redirect()->back()->with('success', 'Kempen / Dana baharu berjaya dibuka dan disiarkan ke Portal Awam!');
    }

    public function updateCampaign(Request $request, $id)
    {
        if (!Auth::user()->isAjk()) {
            return redirect()->back()->with('error', 'Anda tidak mempunyai kebenaran untuk melakukan tindakan ini!');
        }

        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['is_active'] = 1;

        if ($request->hasFile('image')) {
            if ($campaign->image_path && Storage::disk('public')->exists($campaign->image_path)) {
                Storage::disk('public')->delete($campaign->image_path);
            }
            $path = $request->file('image')->store('campaigns', 'public');
            $validated['image_path'] = $path;
        }

        $campaign->update($validated);

        return redirect()->back()->with('success', "Kempen '{$campaign->title}' berjaya dikemaskini!");
    }

    public function destroyCampaign($id)
    {
        if (!Auth::user()->isAjk()) {
            return redirect()->back()->with('error', 'Anda tidak mempunyai kebenaran untuk melakukan tindakan ini!');
        }

        $campaign = Campaign::findOrFail($id);

        if ($campaign->image_path && Storage::disk('public')->exists($campaign->image_path)) {
            Storage::disk('public')->delete($campaign->image_path);
        }

        $campaign->delete();

        return redirect()->back()->with('success', 'Kempen berjaya dipadam daripada sistem!');
    }

    // Process Derma Awam
    public function processDonation(Request $request, $slug)
    {
        $campaign = Campaign::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'required|email|max:255',
            'donor_phone' => 'required|string|max:20',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:toyyibpay,bank_transfer,qr_pay',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $validated['donor_email'])->first();
        if (!$user) {
            $user = User::create([
                'name' => $validated['donor_name'],
                'email' => $validated['donor_email'],
                'password' => Hash::make(Str::random(12)),
            ]);
        }

        Auth::login($user);

        Donor::updateOrCreate(
            ['email' => $validated['donor_email']],
            ['name' => $validated['donor_name'], 'phone' => $validated['donor_phone']]
        );

        $datePrefix = date('Y-md');
        $receiptPrefix = 'REC-' . $datePrefix . '-';
        $todayCount = DonationReceipt::where('receipt_no', 'LIKE', $receiptPrefix . '%')->count();
        $nextSeq = $todayCount + 1;
        $receiptNo = $receiptPrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $receipt = DonationReceipt::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'receipt_no' => $receiptNo,
            'donor_name' => $validated['donor_name'],
            'donor_phone' => $validated['donor_phone'],
            'donor_email' => $validated['donor_email'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'status' => ($validated['payment_method'] === 'toyyibpay') ? 'pending' : 'paid',
            'donated_at' => now(),
            'description' => 'Sumbangan Bagi ' . $campaign->title,
        ]);

        if ($validated['payment_method'] === 'toyyibpay') {
            return $this->createToyyibpayBill($receipt, $campaign);
        } else {
            $campaign->increment('collected_amount', $receipt->amount);

            try {
                Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            } catch (\Exception $e) {}

            return redirect()->route('receipts.index')->with('success', 'Sumbangan anda berjaya direkodkan! Salinan resit telah dihantar ke emel anda.');
        }
    }

    // Fungsi Pembinaan Bil ToyyibPay Kalis Ralat
    private function createToyyibpayBill($receipt, $campaign)
    {
        $baseUrl = env('TOYYIBPAY_URL', 'https://toyyibpay.com');
        $secretKey = env('TOYYIBPAY_SECRET_KEY');
        $categoryCode = env('TOYYIBPAY_CATEGORY_CODE');

        $amountInSen = (int) round($receipt->amount * 100);

        $cleanPhone = preg_replace('/[^0-9]/', '', $receipt->donor_phone);
        if (empty($cleanPhone)) {
            $cleanPhone = '0190000000';
        }

        $cleanBillName = Str::limit($campaign->title, 30, '');

        $option = [
            'userSecretKey' => trim($secretKey),
            'categoryCode' => trim($categoryCode),
            'billName' => $cleanBillName,
            'billDescription' => 'No Resit: ' . $receipt->receipt_no,
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $amountInSen,
            'billReturnUrl' => route('toyyibpay.return'),
            'billCallbackUrl' => route('toyyibpay.callback'),
            'billExternalReferenceNo' => $receipt->receipt_no,
            'billTo' => Str::limit($receipt->donor_name, 50, ''),
            'billEmail' => $receipt->donor_email,
            'billPhone' => $cleanPhone,
            'billPaymentChannel' => '0',
        ];

        try {
            $response = Http::withoutVerifying()
                ->asForm()
                ->post($baseUrl . '/index.php/api/createBill', $option);

            if ($response->successful()) {
                $responseData = $response->json();

                if (is_array($responseData) && isset($responseData[0]['BillCode'])) {
                    $billCode = $responseData[0]['BillCode'];
                    $receipt->update(['toyyibpay_bill_code' => $billCode]);

                    return redirect($baseUrl . '/' . $billCode);
                }

                $errMsg = is_array($responseData) && isset($responseData['msg']) ? $responseData['msg'] : json_encode($responseData);
                return redirect()->back()->with('error', 'ToyyibPay Error: ' . $errMsg);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ralat Sambungan ToyyibPay: ' . $e->getMessage());
        }

        return redirect()->back()->with('error', 'Gagal membina bil ToyyibPay. Sila semak Secret Key & Category Code.');
    }

    // Callback / Return URL ToyyibPay Kalis Parameter (billcode / billCode / order_id)
    public function toyyibpayReturn(Request $request)
    {
        $statusId = $request->get('status_id');
        $billCode = $request->get('billcode') ?? $request->get('billCode');
        $orderId = $request->get('order_id') ?? $request->get('refno');

        // Cari resit berdasarkan BillCode ATAU No. Resit (order_id)
        $receipt = null;
        if (!empty($billCode)) {
            $receipt = DonationReceipt::where('toyyibpay_bill_code', $billCode)->first();
        }

        if (!$receipt && !empty($orderId)) {
            $receipt = DonationReceipt::where('receipt_no', $orderId)->first();
        }

        // Jika masih tiada, ambil resit pending terkini bagi user yang log masuk
        if (!$receipt && Auth::check()) {
            $receipt = DonationReceipt::where('user_id', Auth::id())
                        ->latest('id')
                        ->first();
        }

        if (!$receipt) {
            return redirect()->route('receipts.index')->with('error', 'Rekod resit tidak dijumpai dalam pangkalan data.');
        }

        // Status ID 1 = Bayaran Berjaya di ToyyibPay
        if ($statusId == 1 || $request->get('msg') === 'ok') {
            $receipt->update(['status' => 'paid']);
            
            if ($receipt->campaign) {
                $receipt->campaign->increment('collected_amount', $receipt->amount);
            }

            try {
                Mail::to($receipt->donor_email)->send(new DonationReceiptMail($receipt));
            } catch (\Exception $e) {}

            return redirect()->route('receipts.index')->with('success', 'Sumbangan ToyyibPay anda berjaya! Salinan resit telah dihantar ke emel anda.');
        }

        $receipt->update(['status' => 'failed']);
        return redirect()->route('receipts.index')->with('error', 'Pembayaran ToyyibPay tidak berjaya atau telah dibatalkan.');
    }
}