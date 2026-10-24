<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use InvoiceShelf\Modules\Contracts\Host\ModuleAuthorization;

/** Grants exactly the abilities a test lists, and records what was asked. */
final class FakeAuthorization implements ModuleAuthorization
{
    /** @var list<string> */
    public array $granted = ['e-invoicing:manage-settings', 'view-invoice'];

    /** @var list<array{int, int, string, ?string}> */
    public array $asked = [];

    public function allows(int $userId, int $companyId, string $ability, ?string $resource = null): bool
    {
        $this->asked[] = [$userId, $companyId, $ability, $resource];

        return in_array($ability, $this->granted, true);
    }
}
