<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;
use Modules\EInvoicing\Tests\Support\InvoiceDocuments as Docs;

/**
 * The invoices that must become valid e-invoices, shared by the builder tests
 * and the Schematron run (XSD validity does not check the EN 16931 business
 * rules; Mustang does). Each is the host's worked example with one thing
 * changed, amounts kept consistent the way the host stores them.
 */
final class Scenarios
{
    /** @return array<string, array{InvoiceDocument, ?string}> name => [document, payment terms] */
    public static function valid(): array
    {
        return [
            'standard' => [self::standard(), null],
            'credit note' => [self::creditNote(), null],
            'part paid' => [self::partPaid(), null],
            'exempt' => [self::exempt(), null],
            'per-item rates' => [self::perItemRates(), null],
            'document discount' => [self::documentDiscount(), null],
            'line discount' => [self::lineDiscount(), null],
            'down payment' => [self::downPayment(), null],
            'deduction and discount' => [self::deductionAndDiscount(), null],
            'payment terms' => [self::withoutDueDate(), 'Payable within 14 days without deduction.'],
            'reverse charge' => [self::reverseCharge(), null],
            'intra-community supply' => [self::intraCommunity(), null],
        ];
    }

    public static function standard(): InvoiceDocument
    {
        return Docs::make();
    }

    public static function creditNote(): InvoiceDocument
    {
        return Docs::make(static fn (array $d): array => array_replace($d, [
            'type' => 'CREDIT_NOTE',
            'number' => 'CN-000001',
            'reference_number' => 'INV-000041',
            'related' => ['id' => 6, 'number' => 'INV-000041', 'date' => '2026-01-15'],
            'due_date' => null,
            'sub_total' => -25000,
            'tax' => -4750,
            'total' => -29750,
            'due_amount' => 0,
            'lines' => [
                Docs::line(1, 'Consulting', -10000, '1', -10000),
                Docs::line(2, 'Support', -7500, '2', -15000),
            ],
            'taxes' => [Docs::tax(-4750)],
        ]));
    }

    public static function partPaid(): InvoiceDocument
    {
        return Docs::make(static fn (array $d): array => array_replace($d, ['due_amount' => 9750]));
    }

    public static function exempt(): InvoiceDocument
    {
        return Docs::make(self::documentTaxes([Docs::tax(0, '0', 'E', '§ 19 UStG')], 0, 25000));
    }

    public static function perItemRates(): InvoiceDocument
    {
        return Docs::make(static fn (array $d): array => array_replace($d, [
            'tax_per_item' => true,
            'taxes' => [],
            'tax' => 2950,
            'total' => 27950,
            'due_amount' => 27950,
            'lines' => [
                ['taxes' => [Docs::tax(1900)]] + $d['lines'][0],
                ['taxes' => [Docs::tax(1050, '7')]] + $d['lines'][1],
            ],
        ]));
    }

    public static function documentDiscount(): InvoiceDocument
    {
        return Docs::make(
            static fn (array $d): array => array_replace($d, ['discount_val' => 5000]),
            self::documentTaxes([Docs::tax(3800)], 3800, 23800),
        );
    }

    public static function lineDiscount(): InvoiceDocument
    {
        return Docs::make(
            static function (array $d): array {
                $d['lines'][0] = array_replace($d['lines'][0], ['discount_val' => 2000, 'total' => 8000]);

                return array_replace($d, ['discount_per_item' => true, 'sub_total' => 23000]);
            },
            self::documentTaxes([Docs::tax(4370)], 4370, 27370),
        );
    }

    public static function downPayment(): InvoiceDocument
    {
        return Docs::make(static fn (array $d): array => array_replace($d, [
            'tax_per_item' => true,
            'taxes' => [],
            'sub_total' => 15000,
            'tax' => 2850,
            'total' => 17850,
            'due_amount' => 17850,
            'lines' => [
                ['taxes' => [Docs::tax(1900)]] + $d['lines'][0],
                ['taxes' => [Docs::tax(2850)]] + $d['lines'][1],
                Docs::line(3, 'Anzahlung', -10000, '1', -10000, [Docs::tax(-1900)]),
            ],
        ]));
    }

    public static function deductionAndDiscount(): InvoiceDocument
    {
        return Docs::make(static fn (array $d): array => array_replace($d, [
            'tax_per_item' => true,
            'taxes' => [],
            'discount_val' => 5000,
            'sub_total' => 10000,
            'tax' => 1900,
            'total' => 11900,
            'due_amount' => 11900,
            'lines' => [
                ['taxes' => [Docs::tax(1900)]] + $d['lines'][0],
                ['taxes' => [Docs::tax(2850)]] + $d['lines'][1],
                Docs::line(3, 'Anzahlung', -10000, '1', -10000, [Docs::tax(-2850)]),
            ],
        ]));
    }

    public static function withoutDueDate(): InvoiceDocument
    {
        return Docs::make(static fn (array $d): array => array_replace($d, ['due_date' => null]));
    }

    public static function reverseCharge(): InvoiceDocument
    {
        return Docs::make(self::documentTaxes([Docs::tax(0, '0', 'AE', 'Reverse charge')], 0, 25000));
    }

    public static function intraCommunity(): InvoiceDocument
    {
        return Docs::make(
            self::documentTaxes([Docs::tax(0, '0', 'K', 'Intra-community supply')], 0, 25000),
            static function (array $d): array {
                $d['buyer']['tax_id'] = 'FR12345678901';
                $d['buyer']['address'] = array_replace($d['buyer']['address'], ['city' => 'Paris', 'zip' => '75001', 'country_code' => 'FR', 'state' => null]);

                return $d;
            },
        );
    }

    /** Swap the document taxes for these, with the totals that go with them. */
    public static function documentTaxes(array $taxes, int $tax, int $total): callable
    {
        return static fn (array $d): array => array_replace($d, ['taxes' => $taxes, 'tax' => $tax, 'total' => $total, 'due_amount' => $total]);
    }
}
