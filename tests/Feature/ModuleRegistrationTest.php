<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Feature;

use InvoiceShelf\Modules\Manifest\ModuleManifest;
use InvoiceShelf\Modules\Registry;
use Modules\EInvoicing\Pdf\FacturXAttachmentProvider;
use Modules\EInvoicing\Support\ModuleRegistration;
use Modules\EInvoicing\Tests\TestCase;

final class ModuleRegistrationTest extends TestCase
{
    public function test_it_registers_its_assets_its_ability_and_its_pdf_attachment_provider(): void
    {
        $modulePath = dirname(__DIR__, 2);

        ModuleRegistration::register($modulePath);

        self::assertSame(realpath($modulePath.'/dist/init.js'), Registry::scriptFor('e-invoicing'));
        self::assertSame(realpath($modulePath.'/dist/style.css'), Registry::styleFor('e-invoicing'));
        self::assertSame([[
            'ability' => 'e-invoicing:manage-settings',
            'name' => 'Manage e-invoicing settings',
            'model' => null,
            'depends_on' => [],
            'owner_only' => true,
        ]], Registry::abilitiesFor('e-invoicing'));
        self::assertSame(
            ['e-invoicing' => ['class' => FacturXAttachmentProvider::class]],
            Registry::allDrivers(Registry::INVOICE_PDF_ATTACHMENT),
        );
    }

    public function test_the_manifest_declares_what_the_module_relies_on(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('e-invoicing', $manifest['slug']);
        self::assertSame('bundled', $manifest['dependency_policy']);
        self::assertSame('^1.5.0', $manifest['compatibility']['module_api']);
        self::assertSame(['dist/init.js', 'dist/style.css'], $manifest['assets']);
        self::assertTrue(class_exists(ModuleManifest::class));
    }
}
