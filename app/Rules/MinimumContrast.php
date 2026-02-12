<?php

namespace App\Rules;

use App\Support\Color\Contrast;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MinimumContrast implements ValidationRule
{
    public function __construct(
        private readonly string $backgroundHex = '#FFFFFF',
        private readonly float $minimumRatio = 4.5,
        private readonly ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = Contrast::normalizeHex(is_string($value) ? $value : null);

        if ($normalized === null) {
            return;
        }

        $ratio = Contrast::contrastRatio($this->backgroundHex, $normalized);

        if ($ratio === null || $ratio < $this->minimumRatio) {
            $fail($this->message ?? 'Selected color does not meet the minimum contrast requirement.');
        }
    }
}
