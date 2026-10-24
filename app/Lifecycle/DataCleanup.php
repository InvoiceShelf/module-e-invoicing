<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Lifecycle;

use InvoiceShelf\Modules\Contracts\DataCleanup as DataCleanupContract;
use InvoiceShelf\Modules\Contracts\Host\SettingsStore;
use Modules\EInvoicing\Application\EInvoicingSettings;

/**
 * Removes the module's settings from every company. The module has no
 * tables and writes no files; cached XML expires on its own.
 */
final class DataCleanup implements DataCleanupContract
{
    public function __construct(private readonly SettingsStore $settings) {}

    public function cleanup(): void
    {
        foreach (EInvoicingSettings::KEYS as $key) {
            $this->settings->deleteCompanyForAll(EInvoicingSettings::PREFIX.$key);
        }
    }
}
