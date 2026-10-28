<?php

namespace App\Exceptions;

use RuntimeException;

class VoucherReadinessException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
