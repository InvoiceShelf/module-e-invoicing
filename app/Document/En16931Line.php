<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

/** An invoice line (BG-25). Net amount = quantity x net price - allowance. */
final class En16931Line
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $quantity,
        public readonly string $unitCode,
        public readonly int $netPrice,
        public readonly int $allowance,
        public readonly int $netAmount,
        public readonly string $categoryCode,
        public readonly float $rate,
    ) {}
}
