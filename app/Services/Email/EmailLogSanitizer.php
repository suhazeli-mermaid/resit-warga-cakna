<?php

namespace App\Services\Email;

use Throwable;

class EmailLogSanitizer
{
    public function failure(Throwable|string $failure): string
    {
        $message = $failure instanceof Throwable ? $failure->getMessage() : $failure;
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $message) ?? '';
        $message = preg_replace(
            '/(?i)\b(password|passwd|token|secret|api[_-]?key|authorization|credential)\b\s*[:=]\s*[^\s,;]+/',
            '$1=[REDACTED]',
            $message,
        ) ?? '';
        $message = preg_replace('/(?i)(smtp|smtps):\/\/[^\s@]+@/', '$1://[REDACTED]@', $message) ?? '';
        $message = preg_replace('/(?i)([?&](?:token|key|secret|signature|password)=)[^&\s]+/', '$1[REDACTED]', $message) ?? '';
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');

        return mb_substr($message !== '' ? $message : 'Penghantaran gagal pada lapisan aplikasi.', 0, 1000);
    }
}
