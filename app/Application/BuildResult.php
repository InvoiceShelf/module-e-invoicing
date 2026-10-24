<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Application;

/** Either the e-invoice XML or the requirements standing in its way, never both. */
final class BuildResult
{
    /** @param  list<Requirement>  $missing */
    private function __construct(
        public readonly ?string $xml,
        public readonly array $missing,
    ) {}

    public static function valid(string $xml): self
    {
        return new self($xml, []);
    }

    /** @param  non-empty-list<Requirement>  $missing */
    public static function incomplete(array $missing): self
    {
        if ($missing === []) {
            throw new \InvalidArgumentException('An incomplete result names at least one requirement.');
        }

        return new self(null, $missing);
    }

    public function isValid(): bool
    {
        return $this->xml !== null;
    }

    /** @return list<string> */
    public function missingKeys(): array
    {
        return array_map(static fn (Requirement $requirement): string => $requirement->value, $this->missing);
    }
}
