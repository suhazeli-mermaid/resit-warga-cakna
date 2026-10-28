<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SecureImageDimensions implements ValidationRule
{
    public function __construct(
        private readonly int $minWidth = 100,
        private readonly int $minHeight = 100,
        private readonly int $maxWidth = 3000,
        private readonly int $maxHeight = 3000,
    ) {}

    /**
     * Validate dimensions using the decoded image header. MIME and extension
     * validation remain the responsibility of Laravel's File::image() rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $path = $value->getRealPath();
        $image = $path ? @getimagesize($path) : false;

        // File::image() supplies the invalid-image message when decoding fails.
        if ($image === false) {
            return;
        }

        [$width, $height] = $image;

        if (
            $width < $this->minWidth
            || $height < $this->minHeight
            || $width > $this->maxWidth
            || $height > $this->maxHeight
        ) {
            $fail("Dimensi :attribute mestilah antara {$this->minWidth} × {$this->minHeight} dan {$this->maxWidth} × {$this->maxHeight} piksel.");
        }
    }
}
