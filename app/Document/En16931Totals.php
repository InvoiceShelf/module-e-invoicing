<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

/**
 * The document totals (BG-22): BT-106 line total, BT-107 allowances, BT-109
 * taxable amount, BT-110 tax, BT-112 grand total, BT-113 paid, BT-115 due.
 */
final class En16931Totals
{
    public function __construct(
        public readonly int $lineTotal,
        public readonly int $allowanceTotal,
        public readonly int $taxBasis,
        public readonly int $taxTotal,
        public readonly int $grandTotal,
        public readonly int $prepaid,
        public readonly int $duePayable,
    ) {}
}
