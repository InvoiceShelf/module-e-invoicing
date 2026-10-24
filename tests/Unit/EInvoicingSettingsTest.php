<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Unit;

use Modules\EInvoicing\Application\EInvoicingSettings;
use Modules\EInvoicing\Lifecycle\DataCleanup;
use Modules\EInvoicing\Tests\TestCase;

final class EInvoicingSettingsTest extends TestCase
{
    public function test_settings_are_stored_normalised_under_the_module_prefix(): void
    {
        $settings = new EInvoicingSettings($this->settings);

        $settings->save(self::COMPANY, ['enabled' => true, 'iban' => 'de02 1203 0000 0000 2020 51', 'bic' => ' byladem1001 ', 'payment_terms' => '  Net 14 days  ']);

        self::assertSame([
            'module.e-invoicing.enabled' => 'YES',
            'module.e-invoicing.iban' => 'DE02120300000000202051',
            'module.e-invoicing.bic' => 'BYLADEM1001',
            'module.e-invoicing.payment_terms' => 'Net 14 days',
        ], $this->settings->company[self::COMPANY]);
        self::assertSame(['enabled' => true, 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'payment_terms' => 'Net 14 days'], $settings->forCompany(self::COMPANY));
    }

    public function test_clearing_a_value_removes_it_and_another_company_is_untouched(): void
    {
        $settings = new EInvoicingSettings($this->settings);
        $settings->save(self::COMPANY, ['enabled' => true, 'iban' => self::IBAN, 'bic' => 'BYLADEM1001', 'payment_terms' => 'Net 14']);
        $settings->save(2, ['enabled' => true, 'iban' => self::IBAN]);

        $settings->save(self::COMPANY, ['enabled' => false, 'iban' => '', 'bic' => null, 'payment_terms' => ' ']);

        self::assertSame(['module.e-invoicing.enabled' => 'NO'], $this->settings->company[self::COMPANY]);
        self::assertTrue($settings->isEnabled(2));
        self::assertSame(self::IBAN, $settings->iban(2));
    }

    public function test_uninstalling_with_data_removal_clears_every_company_and_is_safe_to_repeat(): void
    {
        $this->configureSeller();
        (new EInvoicingSettings($this->settings))->save(2, ['enabled' => true, 'iban' => self::IBAN]);
        $this->settings->putCompany(self::COMPANY, 'currency', 3);

        $cleanup = new DataCleanup($this->settings);
        $cleanup->cleanup();
        $cleanup->cleanup();

        self::assertSame(['currency' => 3], $this->settings->company[self::COMPANY]);
        self::assertSame([], $this->settings->company[2]);
    }
}
