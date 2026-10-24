<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\EInvoicing\Application\EInvoicingSettings;

/**
 * A BIC (ISO 9362): bank, country and location codes, with an optional
 * three-character branch code, so 8 or 11 characters. Spaces are ignored.
 */
final class Bic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', (string) EInvoicingSettings::bankIdentifier($value)) !== 1) {
            $fail('einvoicing::validation.bic')->translate();
        }
    }
}
