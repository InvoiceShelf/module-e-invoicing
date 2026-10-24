<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

/** A document-level allowance (BG-20) in one VAT group: a discount or a deduction such as a down payment. */
final class En16931Allowance
{
    public function __construct(
        public readonly int $amount,
        public readonly string $reason,
        public readonly string $categoryCode,
        public readonly float $rate,
    ) {}
}
