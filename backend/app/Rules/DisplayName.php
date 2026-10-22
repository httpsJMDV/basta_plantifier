<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Normalizer;

class DisplayName implements ValidationRule
{
    public static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', Normalizer::normalize($value, Normalizer::FORM_C) ?: $value));
    }

    public static function rules(): array
    {
        return ['required', 'string', 'max:20', new self];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\p{L}[\p{L}\p{M}]*(?: \p{L}[\p{L}\p{M}]*)*$/uD', $value)) {
            $fail('Use letters and spaces only for your display name (up to 20 characters).');
        }
    }
}
