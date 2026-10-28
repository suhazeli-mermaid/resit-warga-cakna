<?php

namespace App\Services\Billplz;

use App\Exceptions\BillplzApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BillplzClient
{
    /**
     * @param  array<string, int|string>  $payload
     * @return array<string, mixed>
     */
    public function createBill(array $payload): array
    {
        $secretKey = trim((string) config('services.billplz.api_secret_key'));
        $baseUrl = rtrim((string) config('services.billplz.base_url'), '/');

        if ($secretKey === '' || ! $this->isSecureBaseUrl($baseUrl)) {
            throw new BillplzApiException('Billplz configuration is incomplete.');
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->acceptJson()
                ->timeout(max(1, min(30, (int) config('services.billplz.timeout', 15))))
                ->post($baseUrl.'/api/v3/bills', $payload);
        } catch (ConnectionException $exception) {
            throw new BillplzApiException('Unable to connect to Billplz.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new BillplzApiException('Billplz rejected the bill creation request.');
        }

        $bill = $response->json();

        if (! is_array($bill)) {
            throw new BillplzApiException('Billplz returned an invalid response.');
        }

        return $bill;
    }

    private function isSecureBaseUrl(string $baseUrl): bool
    {
        return filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === 'https';
    }
}
