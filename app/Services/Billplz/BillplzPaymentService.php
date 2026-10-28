<?php

namespace App\Services\Billplz;

use App\Exceptions\BillplzApiException;
use App\Models\DonationReceipt;
use Illuminate\Support\Str;

class BillplzPaymentService
{
    public function __construct(private BillplzClient $client) {}

    public function isConfigured(): bool
    {
        $baseUrl = rtrim((string) config('services.billplz.base_url'), '/');

        return filled(config('services.billplz.api_secret_key'))
            && filled(config('services.billplz.collection_id'))
            && filled(config('services.billplz.x_signature_key'))
            && filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === 'https';
    }

    public function createBill(DonationReceipt $receipt): string
    {
        if (! $this->isConfigured()) {
            throw new BillplzApiException('Billplz configuration is incomplete.');
        }

        $collectionId = trim((string) config('services.billplz.collection_id'));
        $payload = [
            'collection_id' => $collectionId,
            'name' => Str::limit($receipt->donor_name, 255, ''),
            'amount' => $this->amountToSen($receipt->amount),
            'description' => Str::limit(
                $receipt->description ?: 'Sumbangan Kebajikan WARGA CAKNA',
                200,
                '',
            ),
            'callback_url' => route('billplz.callback'),
            'redirect_url' => route('billplz.redirect'),
            'reference_1_label' => 'Receipt No',
            'reference_1' => Str::limit($receipt->receipt_no, 120, ''),
        ];

        if (filled($receipt->donor_email)) {
            $payload['email'] = $receipt->donor_email;
        }

        $mobile = $this->normalizeMobile($receipt->donor_phone);
        if ($mobile !== '') {
            $payload['mobile'] = $mobile;
        }

        if (! isset($payload['email']) && ! isset($payload['mobile'])) {
            throw new BillplzApiException('Billplz requires an email address or mobile number.');
        }

        $bill = $this->client->createBill($payload);
        $billId = is_string($bill['id'] ?? null) ? trim($bill['id']) : '';
        $billUrl = is_string($bill['url'] ?? null) ? trim($bill['url']) : '';
        $responseCollectionId = is_string($bill['collection_id'] ?? null)
            ? trim($bill['collection_id'])
            : '';

        if ($billId === '' || strlen($billId) > 100 || ! $this->isTrustedBillUrl($billUrl)) {
            throw new BillplzApiException('Billplz returned incomplete bill details.');
        }

        if ($responseCollectionId !== '' && ! hash_equals($collectionId, $responseCollectionId)) {
            throw new BillplzApiException('Billplz returned an unexpected collection.');
        }

        $receipt->forceFill([
            'payment_gateway' => DonationReceipt::GATEWAY_BILLPLZ,
            'gateway_bill_id' => $billId,
            'gateway_collection_id' => $collectionId,
        ])->save();

        return $billUrl;
    }

    public function amountToSen(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function normalizeMobile(?string $mobile): string
    {
        $mobile = preg_replace('/[^0-9+]/', '', (string) $mobile) ?? '';

        if (str_starts_with($mobile, '+')) {
            return '+'.preg_replace('/\D/', '', substr($mobile, 1));
        }

        $digits = preg_replace('/\D/', '', $mobile) ?? '';

        if (str_starts_with($digits, '0')) {
            return '60'.substr($digits, 1);
        }

        return $digits;
    }

    private function isTrustedBillUrl(string $url): bool
    {
        $baseUrl = rtrim((string) config('services.billplz.base_url'), '/');
        $billHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && $baseHost !== ''
            && $billHost !== ''
            && hash_equals($baseHost, $billHost);
    }
}
