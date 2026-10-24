<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use InvoiceShelf\Modules\Contracts\Host\SettingsStore;

final class MemorySettingsStore implements SettingsStore
{
    /** @var array<string, mixed> */
    public array $global = [];

    /** @var array<int, array<string, mixed>> */
    public array $company = [];

    public function getGlobal(string $key, mixed $default = null): mixed
    {
        return $this->global[$key] ?? $default;
    }

    public function putGlobal(string $key, mixed $value): void
    {
        $this->global[$key] = $value;
    }

    public function deleteGlobal(string $key): void
    {
        unset($this->global[$key]);
    }

    public function getCompany(int $companyId, string $key, mixed $default = null): mixed
    {
        return $this->company[$companyId][$key] ?? $default;
    }

    public function putCompany(int $companyId, string $key, mixed $value): void
    {
        $this->company[$companyId][$key] = $value;
    }

    public function deleteCompany(int $companyId, string $key): void
    {
        unset($this->company[$companyId][$key]);
    }

    public function deleteCompanyForAll(string $key): void
    {
        foreach (array_keys($this->company) as $companyId) {
            unset($this->company[$companyId][$key]);
        }
    }
}
