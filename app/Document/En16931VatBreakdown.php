<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

/** One VAT breakdown group (BG-23): a category and rate with its taxable amount and tax. */
final class En16931VatBreakdown
{
    public function __construct(
        public readonly string $categoryCode,
        public readonly float $rate,
        public readonly int $basis,
        public readonly int $tax,
        public readonly ?string $exemptionReason,
    ) {}
}
