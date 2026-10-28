<?php

namespace App\Services\Billplz;

class XSignatureVerifier
{
    /**
     * Verify a flat Billplz callback payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        return $this->verify($payload, $payload['x_signature'] ?? null);
    }

    /**
     * Verify the nested billplz[...] redirect query exactly as received.
     *
     * @param  array<string, mixed>  $query
     */
    public function verifyRedirect(array $query): bool
    {
        $signature = is_array($query['billplz'] ?? null)
            ? ($query['billplz']['x_signature'] ?? null)
            : null;

        return $this->verify($query, $signature);
    }

    /**
     * Produce the HMAC source digest defined by Billplz. This method is also
     * useful for deterministic conformance tests against Billplz vectors.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload, ?string $key = null): string
    {
        $key ??= (string) config('services.billplz.x_signature_key');

        if ($key === '') {
            return '';
        }

        $sourceStrings = [];
        $this->flatten($payload, '', $sourceStrings);
        usort($sourceStrings, 'strcasecmp');

        return hash_hmac('sha256', implode('|', $sourceStrings), $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verify(array $payload, mixed $receivedSignature): bool
    {
        $key = (string) config('services.billplz.x_signature_key');

        if ($key === '' || ! is_string($receivedSignature) || $receivedSignature === '') {
            return false;
        }

        $expectedSignature = $this->sign($payload, $key);

        return $expectedSignature !== ''
            && hash_equals($expectedSignature, strtolower($receivedSignature));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $sourceStrings
     */
    private function flatten(array $values, string $prefix, array &$sourceStrings): void
    {
        foreach ($values as $key => $value) {
            if ((string) $key === 'x_signature') {
                continue;
            }

            $sourceKey = $prefix.(string) $key;

            if (is_array($value)) {
                $this->flatten($value, $sourceKey, $sourceStrings);

                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            } elseif (! is_scalar($value)) {
                continue;
            }

            $sourceStrings[] = $sourceKey.(string) $value;
        }
    }
}
