<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Modules\EInvoicing\Rules\Bic;
use Modules\EInvoicing\Rules\Iban;
use Modules\EInvoicing\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BankIdentifierRulesTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function ibans(): array
    {
        return [
            'German, spaced' => ['DE02 1203 0000 0000 2020 51', true],
            'Austrian' => ['AT611904300234573201', true],
            'Belgian, lower case' => ['be68539007547034', true],
            'checksum off by one' => ['DE03120300000000202051', false],
            'too short' => ['DE021203', false],
            'not an IBAN' => ['Sparkasse, Konto 12345', false],
        ];
    }

    #[DataProvider('ibans')]
    public function test_an_iban_needs_its_shape_and_a_mod_97_checksum(string $iban, bool $valid): void
    {
        self::assertSame($valid, Validator::make(['iban' => $iban], ['iban' => [new Iban]])->passes());
    }

    /** @return array<string, array{string, bool}> */
    public static function bics(): array
    {
        return [
            'eight characters' => ['BYLADEMM', true],
            'eleven characters' => ['BYLADEM1001', true],
            'spaced, lower case' => ['byla de m1 001', true],
            'nine characters' => ['BYLADEM10', false],
            'digits in the bank code' => ['B1LADEMM', false],
        ];
    }

    #[DataProvider('bics')]
    public function test_a_bic_has_eight_or_eleven_characters(string $bic, bool $valid): void
    {
        self::assertSame($valid, Validator::make(['bic' => $bic], ['bic' => [new Bic]])->passes());
    }

    public function test_a_refusal_explains_itself(): void
    {
        $errors = Validator::make(['iban' => 'nope'], ['iban' => [new Iban]])->errors();

        self::assertStringContainsString('valid IBAN', (string) $errors->first('iban'));
    }
}
