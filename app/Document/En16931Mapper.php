<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

use InvoiceShelf\Modules\Documents\Data\DocumentLine;
use InvoiceShelf\Modules\Documents\Data\DocumentParty;
use InvoiceShelf\Modules\Documents\Data\DocumentTax;
use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;
use Modules\EInvoicing\Application\Requirement;

/**
 * Turns an invoice into an EN 16931 invoice, or into the requirements it does
 * not meet yet.
 *
 * Amounts are the invoice's stored values and are not recalculated; the
 * summation is assembled so it adds up within the document (BT-106 - BT-107 =
 * BT-109, BT-109 + BT-110 = BT-112). What EN 16931 cannot express from the
 * data (several taxes on one line, tax-inclusive prices, fixed-amount taxes, a
 * rate that contradicts its category) is reported as a requirement, so the
 * invoice keeps its ordinary PDF.
 *
 * A credit note is stored with negative amounts; it is mapped with the signs
 * turned, as type 381 with positive amounts, and refers to the invoice it
 * corrects (BG-3).
 *
 * Ported from the ZUGFeRD work of André Kinne and iebl for InvoiceShelf.
 */
final class En16931Mapper
{
    /** Categories whose rules demand an exemption reason (BT-120). */
    public const EXEMPT_CATEGORIES = ['E', 'AE', 'K', 'G', 'O'];

    /** Categories this version writes; see Requirement::UnsupportedTaxCategory. */
    public const SUPPORTED_CATEGORIES = ['S', 'Z', 'E', 'AE', 'K', 'G'];

    /** Categories that need the buyer's VAT identifier (BR-AE-02, BR-IC-02). */
    private const BUYER_VAT_CATEGORIES = ['AE', 'K'];

    /** Reason text on a discount, in the document's own vocabulary. */
    private const DISCOUNT_REASON = 'Discount';

    /** Reason on a deduction line that has no name to use. */
    private const DEDUCTION_REASON = 'Deduction';

    /**
     * A VAT identifier with its country prefix: the EU members (Greece as EL),
     * Northern Ireland, and the neighbours whose numbers follow the same shape.
     */
    private const VAT_ID_PATTERN = '/^(AT|BE|BG|CY|CZ|DE|DK|EE|EL|ES|FI|FR|HR|HU|IE|IT|LT|LU|LV|MT|NL|PL|PT|RO|SE|SI|SK|XI|CH|GB|NO)[0-9A-Z]{2,13}$/';

    /**
     * @param  string|null  $iban  The seller's IBAN, already validated and normalised.
     */
    public function map(InvoiceDocument $invoice, ?string $iban, ?string $bic, ?string $paymentTerms): MappingResult
    {
        $missing = $this->missingRequirements($invoice, $iban, $paymentTerms);

        if ($missing !== []) {
            return MappingResult::incomplete($missing);
        }

        return MappingResult::mapped($this->compose($invoice, $iban, $bic, $paymentTerms));
    }

    /**
     * The requirements the company's own data does not meet: the gaps that hold
     * for every invoice it will issue, and the only ones it can close in its
     * settings. Empty means ready, not that any particular invoice will pass.
     *
     * @return list<Requirement>
     */
    public function missingSellerRequirements(?DocumentParty $seller, ?string $iban): array
    {
        $missing = [];
        $this->collectSellerRequirements($missing, $seller, $iban, true);

        return $missing;
    }

    /** @return list<Requirement> */
    private function missingRequirements(InvoiceDocument $invoice, ?string $iban, ?string $paymentTerms): array
    {
        $missing = [];
        $buyer = $invoice->buyer;

        $this->requireValue($missing, Requirement::InvoiceNumber, $invoice->number);
        $this->requireValue($missing, Requirement::InvoiceDate, $invoice->invoiceDate);
        $this->requireValue($missing, Requirement::InvoiceCurrency, $invoice->currencyCode);

        $this->collectSellerRequirements($missing, $invoice->seller, $iban, ! $invoice->isCreditNote());

        $this->requireValue($missing, Requirement::BuyerName, $buyer?->name);
        $this->requireValue($missing, Requirement::BuyerCountry, $buyer?->address?->countryCode);

        if ($invoice->taxIncluded) {
            $this->add($missing, Requirement::NetPrices);
        }

        if ($this->positions($invoice) === []) {
            $this->add($missing, Requirement::LineItems);
        }

        foreach ($invoice->lines as $line) {
            if (trim($line->name) === '') {
                $this->add($missing, Requirement::LineItemName);
            }

            if ($this->signed($invoice, $line->price) < 0 && $this->signed($invoice, $line->total) >= 0) {
                $this->add($missing, Requirement::NegativePrice);
            }
        }

        foreach ($this->taxesPerLine($invoice) as $lineTaxes) {
            if ($lineTaxes === []) {
                $this->add($missing, Requirement::InvoiceTax);
            } elseif (count($lineTaxes) > 1) {
                $this->add($missing, Requirement::SingleTaxRatePerLine);
            }
        }

        foreach ($this->applicableTaxes($invoice) as $tax) {
            $category = $tax->categoryCode;

            if ($category === null || $category === '') {
                $this->add($missing, Requirement::TaxCategoryCode);

                continue;
            }

            if (! in_array($category, self::SUPPORTED_CATEGORIES, true)) {
                $this->add($missing, Requirement::UnsupportedTaxCategory);

                continue;
            }

            if ($tax->calculationType === DocumentTax::CALCULATION_FIXED || $tax->percent === null) {
                $this->add($missing, Requirement::TaxRatePercentage);
            } elseif (! $this->rateFitsCategory($category, (float) $tax->percent)) {
                $this->add($missing, Requirement::TaxRateForCategory);
            }

            if (in_array($category, self::EXEMPT_CATEGORIES, true) && trim((string) $tax->exemptionReason) === '') {
                $this->add($missing, Requirement::TaxExemptionReason);
            }

            if (in_array($category, self::BUYER_VAT_CATEGORIES, true) && $this->buyerVatId($invoice) === null) {
                $this->add($missing, Requirement::BuyerVatId);
            }
        }

        if ($this->signed($invoice, $invoice->dueAmount) > 0 && $invoice->dueDate === null && $paymentTerms === null) {
            $this->add($missing, Requirement::PaymentTerms);
        }

        return $missing;
    }

    /**
     * The seller's name, postal address, VAT identifier and, for documents
     * that ask for payment, the IBAN.
     *
     * @param  list<Requirement>  $missing
     */
    private function collectSellerRequirements(array &$missing, ?DocumentParty $seller, ?string $iban, bool $needsIban): void
    {
        $address = $seller?->address;

        $this->requireValue($missing, Requirement::SellerName, $seller?->name);
        $this->requireValue($missing, Requirement::SellerStreet, $address?->street1);
        $this->requireValue($missing, Requirement::SellerPostcode, $address?->zip);
        $this->requireValue($missing, Requirement::SellerCity, $address?->city);
        $this->requireValue($missing, Requirement::SellerCountry, $address?->countryCode);

        // BR-CO-26 accepts only BT-29, BT-30 or BT-31 as the seller's
        // identification, and of those the host holds the VAT identifier
        // (BT-31). A national tax number is BT-32, which the rule does not
        // count, so a tax number alone reads as not ready.
        $this->requireValue($missing, Requirement::SellerTaxRegistration, $seller?->vatId);

        if ($needsIban && $iban === null) {
            $this->add($missing, Requirement::SellerIban);
        }
    }

    /** @param  list<Requirement>  $missing */
    private function requireValue(array &$missing, Requirement $requirement, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->add($missing, $requirement);
        }
    }

    /** @param  list<Requirement>  $missing */
    private function add(array &$missing, Requirement $requirement): void
    {
        if (! in_array($requirement, $missing, true)) {
            $missing[] = $requirement;
        }
    }

    /**
     * The taxes that apply to each line, whichever way the invoice taxes: a
     * document tax applies to every line, which is what EN 16931 asks a line
     * to state (BG-30).
     *
     * @return list<list<DocumentTax>>
     */
    private function taxesPerLine(InvoiceDocument $invoice): array
    {
        return array_map(
            static fn (DocumentLine $line): array => $invoice->taxPerItem ? $line->taxes : $invoice->taxes,
            $invoice->lines,
        );
    }

    /** @return list<DocumentTax> */
    private function applicableTaxes(InvoiceDocument $invoice): array
    {
        if (! $invoice->taxPerItem) {
            return $invoice->taxes;
        }

        return array_merge([], ...array_map(static fn (DocumentLine $line): array => $line->taxes, $invoice->lines));
    }

    /**
     * The lines that become invoice lines (BG-25): all but the deductions.
     *
     * @return list<DocumentLine>
     */
    private function positions(InvoiceDocument $invoice): array
    {
        return array_values(array_filter($invoice->lines, fn (DocumentLine $line): bool => ! $this->isDeduction($invoice, $line)));
    }

    /**
     * A line whose amount is negative, such as a down payment already
     * settled. EN 16931 allows no negative net price (BR-27), so it travels as
     * a document allowance (BG-20) in the VAT group it was taxed in.
     */
    private function isDeduction(InvoiceDocument $invoice, DocumentLine $line): bool
    {
        return $this->signed($invoice, $line->total) < 0;
    }

    /**
     * Standard-rated is above 0 %; zero-rated, exempt, reverse charge,
     * intra-community and export are exactly 0 %.
     */
    private function rateFitsCategory(string $category, float $percent): bool
    {
        return $category === 'S' ? $percent > 0 : abs($percent) < 0.0001;
    }

    /** An amount as the document states it: a credit note's stored amounts turned positive. */
    private function signed(InvoiceDocument $invoice, int $amount): int
    {
        return $invoice->isCreditNote() ? -$amount : $amount;
    }

    /**
     * The buyer's VAT identifier (BT-48). The customer's tax ID is an untyped
     * field that may hold a VAT identifier or a national tax number; only a
     * value with a VAT country prefix is taken as BT-48, so a national number
     * is never misstated as one.
     */
    private function buyerVatId(InvoiceDocument $invoice): ?string
    {
        $candidate = strtoupper((string) preg_replace('/[\s.\-]+/', '', (string) $invoice->buyer?->taxId));

        return preg_match(self::VAT_ID_PATTERN, $candidate) === 1 ? $candidate : null;
    }

    /** Only called once every requirement is met, so the data it reads is there. */
    private function compose(InvoiceDocument $invoice, ?string $iban, ?string $bic, ?string $paymentTerms): En16931Invoice
    {
        $groups = $this->vatGroups($invoice);
        $lineTotal = array_sum(array_column($groups, 'lineTotal'));
        $discount = max(0, $this->signed($invoice, $invoice->discountVal));
        $shares = $this->allocate($discount, array_column($groups, 'lineTotal'));

        $breakdown = [];
        $allowances = [];
        $deducted = 0;
        $taxTotal = 0;
        $taxBasis = 0;

        foreach ($groups as $index => $group) {
            $deduction = array_sum(array_column($group['deductions'], 'amount'));
            $basis = $group['lineTotal'] - $deduction - $shares[$index];
            $deducted += $deduction;
            $taxBasis += $basis;
            $taxTotal += $group['tax'];

            $breakdown[] = new En16931VatBreakdown($group['category'], $group['rate'], $basis, $group['tax'], $group['exemptionReason']);

            foreach ($group['deductions'] as $entry) {
                $allowances[] = new En16931Allowance($entry['amount'], $entry['reason'] ?: self::DEDUCTION_REASON, $group['category'], $group['rate']);
            }

            if ($shares[$index] > 0) {
                $allowances[] = new En16931Allowance($shares[$index], self::DISCOUNT_REASON, $group['category'], $group['rate']);
            }
        }

        $grandTotal = $taxBasis + $taxTotal;
        $prepaid = max(0, $this->signed($invoice, $invoice->total) - $this->signed($invoice, $invoice->dueAmount));
        $buyer = $invoice->buyer ?? new DocumentParty('');
        $categories = array_column($groups, 'category');

        return new En16931Invoice(
            number: $invoice->number,
            typeCode: $invoice->isCreditNote() ? En16931Invoice::TYPE_CREDIT_NOTE : En16931Invoice::TYPE_INVOICE,
            issueDate: $invoice->invoiceDate,
            currencyCode: strtoupper($invoice->currencyCode),
            seller: $invoice->seller,
            buyer: $buyer,
            buyerVatId: $this->buyerVatId($invoice),
            lines: $this->lines($invoice),
            allowances: $allowances,
            vatBreakdown: $breakdown,
            totals: new En16931Totals($lineTotal, $discount + $deducted, $taxBasis, $taxTotal, $grandTotal, $prepaid, $grandTotal - $prepaid),
            // On a credit note the reference number holds the corrected
            // invoice's number, which is BG-3, not the buyer's reference.
            buyerReference: $invoice->isCreditNote() ? null : $this->filled($invoice->referenceNumber),
            note: $this->filled(self::plainText($invoice->notes)),
            precedingInvoice: $invoice->isCreditNote() ? $invoice->related : null,
            // An intra-community supply names the country goods went to (BR-IC-12).
            deliverTo: in_array('K', $categories, true) ? ($invoice->deliveryAddress?->countryCode ? $invoice->deliveryAddress : $buyer->address) : null,
            iban: $invoice->isCreditNote() ? null : $iban,
            bic: $invoice->isCreditNote() ? null : $bic,
            dueDate: $invoice->dueDate,
            paymentTerms: $paymentTerms,
        );
    }

    /** @return list<En16931Line> */
    private function lines(InvoiceDocument $invoice): array
    {
        $lines = [];

        foreach ($this->positions($invoice) as $index => $line) {
            $tax = $this->lineTax($invoice, $line);

            $lines[] = new En16931Line(
                id: (string) ($index + 1),
                name: self::plainText($line->name),
                description: $this->filled(self::plainText($line->description)),
                quantity: (float) $line->quantity,
                unitCode: $line->unitCode ?: 'C62',
                netPrice: abs($line->price),
                allowance: max(0, $this->signed($invoice, $line->discountVal)),
                netAmount: $this->signed($invoice, $line->total),
                categoryCode: (string) $tax->categoryCode,
                rate: round((float) $tax->percent, 2),
            );
        }

        return $lines;
    }

    /**
     * The VAT groups, one per category and rate, in the order the lines
     * introduce them. `lineTotal` sums the net amounts of the invoice lines in
     * the group; a deduction line is a `deductions` entry of the group it was
     * taxed in, and `tax` counts it with its negative sign.
     *
     * @return list<array{category: string, rate: float, exemptionReason: ?string, lineTotal: int, deductions: list<array{reason: string, amount: int}>, tax: int}>
     */
    private function vatGroups(InvoiceDocument $invoice): array
    {
        $groups = [];

        foreach ($invoice->lines as $line) {
            $tax = $this->lineTax($invoice, $line);
            $category = (string) $tax->categoryCode;
            $rate = round((float) $tax->percent, 2);
            $key = $category.'@'.$rate;

            $groups[$key] ??= [
                'category' => $category,
                'rate' => $rate,
                'exemptionReason' => in_array($category, self::EXEMPT_CATEGORIES, true) ? $this->filled(self::plainText($tax->exemptionReason)) : null,
                'lineTotal' => 0,
                'deductions' => [],
                'tax' => 0,
            ];

            if ($this->isDeduction($invoice, $line)) {
                $groups[$key]['deductions'][] = ['reason' => self::plainText($line->name), 'amount' => abs($line->total)];
            } else {
                $groups[$key]['lineTotal'] += $this->signed($invoice, $line->total);
            }

            if ($invoice->taxPerItem) {
                $groups[$key]['tax'] += $this->signed($invoice, $tax->amount);
            }
        }

        if (! $invoice->taxPerItem) {
            // One document tax on the whole invoice: its stored amount belongs
            // to the single group every line shares.
            foreach (array_keys($groups) as $key) {
                $groups[$key]['tax'] = $this->signed($invoice, $invoice->taxes[0]->amount);
            }
        }

        return array_values($groups);
    }

    /**
     * Split an amount across weights in whole hundredths, losing none.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $remaining = $amount;
        $shares = [];
        $last = count($weights) - 1;

        foreach ($weights as $index => $weight) {
            if ($index === $last) {
                $shares[] = $remaining;

                break;
            }

            $share = $total === 0 ? 0 : (int) round($amount * $weight / $total);
            $shares[] = $share;
            $remaining -= $share;
        }

        return $shares;
    }

    /** The single tax that applies to a line. */
    private function lineTax(InvoiceDocument $invoice, DocumentLine $line): DocumentTax
    {
        return $invoice->taxPerItem ? $line->taxes[0] : $invoice->taxes[0];
    }

    private function filled(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * Rich text as the plain text an XML element can carry: markup resolved to
     * line breaks, and the control characters no XML document may hold dropped.
     */
    public static function plainText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $value);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text);

        return trim((string) $text);
    }
}
