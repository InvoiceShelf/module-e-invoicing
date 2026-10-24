<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

use InvoiceShelf\Modules\Documents\Data\DocumentParty;
use InvoiceShelf\Modules\Documents\Data\PostalAddress;
use InvoiceShelf\Modules\Documents\Data\RelatedDocument;

/**
 * An invoice in EN 16931 terms, independent of any syntax: what the CII
 * writer serialises today and a UBL writer can serialise later.
 *
 * Built only by {@see En16931Mapper}, which has already checked every
 * requirement, so the fields a writer needs are present. Money is in
 * hundredths and positive, credit notes included (their type code says what
 * they are). Rates are percentages rounded to two decimals.
 */
final class En16931Invoice
{
    public const TYPE_INVOICE = '380';

    public const TYPE_CREDIT_NOTE = '381';

    /**
     * @param  list<En16931Line>  $lines
     * @param  list<En16931Allowance>  $allowances
     * @param  list<En16931VatBreakdown>  $vatBreakdown
     */
    public function __construct(
        public readonly string $number,
        public readonly string $typeCode,
        public readonly string $issueDate,
        public readonly string $currencyCode,
        public readonly DocumentParty $seller,
        public readonly DocumentParty $buyer,
        public readonly ?string $buyerVatId,
        public readonly array $lines,
        public readonly array $allowances,
        public readonly array $vatBreakdown,
        public readonly En16931Totals $totals,
        public readonly ?string $buyerReference = null,
        public readonly ?string $note = null,
        public readonly ?RelatedDocument $precedingInvoice = null,
        public readonly ?PostalAddress $deliverTo = null,
        public readonly ?string $iban = null,
        public readonly ?string $bic = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $paymentTerms = null,
    ) {}
}
