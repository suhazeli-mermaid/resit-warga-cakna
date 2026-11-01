<?php

namespace App\Services;

class VoucherAcknowledgementUrlService
{
    public function forToken(string $rawToken): string
    {
        $baseUrl = trim((string) config('services.vouchers.public_url'));
        if ($baseUrl === '') {
            $baseUrl = (string) config('app.url');
        }

        $path = route('vouchers.acknowledgement.show', ['token' => $rawToken], false);

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
