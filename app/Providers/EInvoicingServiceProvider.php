<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Providers;

use InvoiceShelf\Modules\Support\ModuleServiceProvider;
use Modules\EInvoicing\Support\ModuleRegistration;

/**
 * EN 16931 e-invoicing: invoice PDFs become ZUGFeRD / Factur-X hybrid
 * e-invoices, and the XML can be downloaded on its own.
 */
final class EInvoicingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'EInvoicing';

    protected string $nameLower = 'einvoicing';

    public function boot(): void
    {
        parent::boot();

        ModuleRegistration::register(module_path($this->name));
        $this->loadRoutesFrom(module_path($this->name, 'routes/api.php'));
    }
}
