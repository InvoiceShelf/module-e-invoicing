<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;

/**
 * Invoices for the tests, built from the host's worked example
 * (tests/Fixtures/invoice-document.standard.json, a copy of the file the
 * host's reader test produces; CI fails when the two drift): a German seller
 * billing 250.00 net in two lines, Consulting 1 hour at 100.00 and Support
 * 2 x 75.00, plus 19 % VAT, 297.50 in all.
 */
final class InvoiceDocuments
{
    /** @return array<string, mixed> */
    public static function standard(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/invoice-document.standard.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The standard invoice, changed by each callback in turn.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  ...$changes
     */
    public static function make(callable ...$changes): InvoiceDocument
    {
        $data = self::standard();

        foreach ($changes as $change) {
            $data = $change($data);
        }

        return InvoiceDocument::fromArray($data);
    }

    /**
     * A tax row like the fixture's, at another rate or category.
     *
     * @return array<string, mixed>
     */
    public static function tax(int $amount, string $percent = '19', string $category = 'S', ?string $reason = null, string $name = 'USt'): array
    {
        return [
            'tax_type_id' => 1,
            'name' => $name,
            'percent' => $percent,
            'amount' => $amount,
            'calculation_type' => 'percentage',
            'fixed_amount' => null,
            'compound' => false,
            'category_code' => $category,
            'exemption_reason' => $reason,
        ];
    }

    /**
     * A line like the fixture's.
     *
     * @param  list<array<string, mixed>>  $taxes
     * @return array<string, mixed>
     */
    public static function line(int $id, string $name, int $price, string $quantity, int $total, array $taxes = [], int $discount = 0): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => null,
            'quantity' => $quantity,
            'price' => $price,
            'discount_val' => $discount,
            'total' => $total,
            'unit_name' => null,
            'unit_code' => null,
            'taxes' => $taxes,
        ];
    }
}
