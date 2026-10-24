<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Support;

use InvoiceShelf\Modules\Registry;
use Modules\EInvoicing\Pdf\FacturXAttachmentProvider;

/** Everything the module registers with the host, from its provider's boot. */
final class ModuleRegistration
{
    public static function register(string $modulePath): void
    {
        Registry::registerScript(Authorizes::SLUG, $modulePath.'/dist/init.js');
        Registry::registerStyle(Authorizes::SLUG, $modulePath.'/dist/style.css');

        Registry::registerAbility(Authorizes::SLUG, [
            'ability' => Authorizes::MANAGE_SETTINGS,
            'name' => 'Manage e-invoicing settings',
            'owner_only' => true,
        ]);

        Registry::registerInvoicePdfAttachmentProvider(Authorizes::SLUG, FacturXAttachmentProvider::class);
    }
}
