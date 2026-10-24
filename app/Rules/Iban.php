<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\EInvoicing\Application\EInvoicingSettings;

/**
 * An IBAN: country code, check digits and the account part, 15 to 34
 * characters, whose ISO 7064 mod-97 checksum holds. Spaces are ignored.
 */
final class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) EInvoicingSettings::bankIdentifier($value))) {
            $fail('einvoicing::validation.iban')->translate();
        }
    }

    public static function isValid(string $iban): bool
    {
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }

        // Move the first four characters to the end, letters to 10..35, and
        // take the remainder piecewise so no number outgrows an integer.
        $digits = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $digits .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }

        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
