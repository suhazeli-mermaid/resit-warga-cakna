<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Services\Billplz\BillplzPaymentService;
use App\Services\Billplz\XSignatureVerifier;
use App\Services\DonationReceiptAuditService;
use App\Services\PaymentNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillplzPaymentController extends Controller
{
    public function __construct(
        private XSignatureVerifier $signatures,
        private BillplzPaymentService $billplz,
        private PaymentNotificationService $notifications,
        private DonationReceiptAuditService $audits,
    ) {}

    public function callback(Request $request)
    {
        if (! $this->billplz->isConfigured()) {
            return response('CONFIGURATION ERROR', 503);
        }

        $payload = $request->all();

        if (! $this->signatures->verifyCallback($payload)) {
            return response('INVALID SIGNATURE', 403);
        }

        foreach (['id', 'collection_id', 'paid', 'state', 'amount', 'paid_amount'] as $key) {
            if (! is_scalar($payload[$key] ?? null) || (string) $payload[$key] === '') {
                return response('INVALID CALLBACK', 422);
            }
        }

        $billId = (string) $payload['id'];
        $collectionId = (string) $payload['collection_id'];

        if (strlen($billId) > 100 || strlen($collectionId) > 100) {
            return response('INVALID CALLBACK', 422);
        }

        if (! ctype_digit((string) $payload['amount']) || ! ctype_digit((string) $payload['paid_amount'])) {
            return response('INVALID AMOUNT', 422);
        }

        $receipt = DonationReceipt::query()
            ->where('payment_gateway', DonationReceipt::GATEWAY_BILLPLZ)
            ->where('gateway_bill_id', $billId)
            ->first();

        if (! $receipt) {
            return response('TRANSACTION NOT FOUND', 404);
        }

        $storedCollectionId = (string) $receipt->gateway_collection_id;
        if ($storedCollectionId === '' || ! hash_equals($storedCollectionId, $collectionId)) {
            return response('COLLECTION MISMATCH', 422);
        }

        $expectedAmount = $this->billplz->amountToSen($receipt->amount);
        if ((int) $payload['amount'] !== $expectedAmount) {
            return response('AMOUNT MISMATCH', 422);
        }

        $paid = (string) $payload['paid'] === 'true';
        $state = (string) $payload['state'];
        $transactionStatus = isset($payload['transaction_status']) && is_scalar($payload['transaction_status'])
            ? (string) $payload['transaction_status']
            : null;

        if ($transactionStatus !== null && strlen($transactionStatus) > 32) {
            return response('INVALID TRANSACTION STATUS', 422);
        }

        // A failed payment leaves its Bill due. It is acknowledged but must not
        // change the authoritative receipt state or trigger notifications.
        if (! $paid || $state !== 'paid') {
            return response('OK', 200);
        }

        if ((int) $payload['paid_amount'] !== $expectedAmount) {
            return response('PAID AMOUNT MISMATCH', 422);
        }

        if ($transactionStatus !== null && $transactionStatus !== 'completed') {
            return response('INVALID TRANSACTION STATUS', 422);
        }

        $reference = isset($payload['transaction_id']) && is_scalar($payload['transaction_id'])
            ? trim((string) $payload['transaction_id'])
            : '';

        if (strlen($reference) > 255) {
            return response('INVALID TRANSACTION REFERENCE', 422);
        }
        $donatedAt = $this->paymentDate($payload['paid_at'] ?? null);

        $completedReceipt = DB::transaction(function () use ($receipt, $reference, $donatedAt) {
            $lockedReceipt = DonationReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->isCompleted() || ! $lockedReceipt->isPending()) {
                return null;
            }

            $lockedReceipt->forceFill([
                'status' => DonationReceipt::STATUS_COMPLETED,
                'payment_reference' => $reference !== '' ? $reference : $lockedReceipt->payment_reference,
                'verified_by' => null,
                'verified_at' => now(),
                'donated_at' => $donatedAt,
                'rejection_reason' => null,
            ])->save();

            if ($lockedReceipt->campaign_id !== null) {
                $campaign = Campaign::query()->lockForUpdate()->find($lockedReceipt->campaign_id);
                $campaign?->increment('collected_amount', $lockedReceipt->amount);
            }

            $this->audits->record(
                $lockedReceipt,
                'verify',
                reason: 'Disahkan melalui callback Billplz X-Signature.',
                actorType: DonationReceipt::GATEWAY_BILLPLZ,
            );

            return $lockedReceipt->fresh();
        });

        if ($completedReceipt) {
            $this->notifications->completed($completedReceipt);
        }

        return response('OK', 200);
    }

    public function redirect(Request $request)
    {
        $query = $request->query();
        $billplz = is_array($query['billplz'] ?? null) ? $query['billplz'] : [];

        if (! $this->signatures->verifyRedirect($query)) {
            return redirect()->route('public.index')
                ->with('error', 'Maklumat pulangan Billplz tidak sah. Status bayaran tidak diubah.');
        }

        $billId = is_scalar($billplz['id'] ?? null) ? (string) $billplz['id'] : '';
        $receipt = DonationReceipt::query()
            ->where('payment_gateway', DonationReceipt::GATEWAY_BILLPLZ)
            ->where('gateway_bill_id', $billId)
            ->first();

        if (! $receipt) {
            return redirect()->route('public.index')
                ->with('error', 'Bil Billplz tidak ditemui. Status bayaran tidak diubah.');
        }

        if ($receipt->isCompleted()) {
            return redirect()->route('public.index')
                ->with('success', 'Pembayaran sumbangan anda telah disahkan BERJAYA.');
        }

        if ((string) ($billplz['paid'] ?? '') === 'true') {
            return redirect()->route('public.index')
                ->with('success', 'Maklum balas bayaran diterima dan sedang menunggu callback selamat Billplz.');
        }

        return redirect()->route('public.index')
            ->with('error', 'Pembayaran masih belum disahkan atau telah dibatalkan.');
    }

    private function paymentDate(mixed $paidAt): string
    {
        if (is_scalar($paidAt) && (string) $paidAt !== '') {
            try {
                return Carbon::parse((string) $paidAt)->toDateString();
            } catch (Throwable) {
                // A valid signed callback with a malformed optional date still
                // uses the server date; payment state remains callback-driven.
            }
        }

        return now()->toDateString();
    }
}
