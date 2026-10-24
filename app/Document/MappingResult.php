<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document;

use Modules\EInvoicing\Application\Requirement;

/** Either an EN 16931 invoice or the requirements standing in its way, never both. */
final class MappingResult
{
    /** @param  list<Requirement>  $missing */
    private function __construct(
        public readonly ?En16931Invoice $invoice,
        public readonly array $missing,
    ) {}

    public static function mapped(En16931Invoice $invoice): self
    {
        return new self($invoice, []);
    }

    /** @param  non-empty-list<Requirement>  $missing */
    public static function incomplete(array $missing): self
    {
        if ($missing === []) {
            throw new \InvalidArgumentException('An incomplete result names at least one requirement.');
        }

        return new self(null, $missing);
    }
}
