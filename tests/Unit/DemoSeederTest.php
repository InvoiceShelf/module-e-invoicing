<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Unit;

use Modules\EInvoicing\Application\EInvoicingSettings;
use Modules\EInvoicing\Demo\DemoSeeder;
use Modules\EInvoicing\Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    public function test_the_demo_reset_switches_e_invoicing_on_with_bank_details_and_terms(): void
    {
        // The call the host's DemoReset makes for each pinned module.
        app(DemoSeeder::class)->setContainer(app())->__invoke(['companyId' => self::COMPANY]);

        self::assertSame([
            'enabled' => true,
            'iban' => DemoSeeder::IBAN,
            'bic' => DemoSeeder::BIC,
            'payment_terms' => DemoSeeder::PAYMENT_TERMS,
        ], app(EInvoicingSettings::class)->forCompany(self::COMPANY));
    }

    public function test_a_company_that_already_has_an_iban_is_left_alone(): void
    {
        $settings = app(EInvoicingSettings::class);
        $settings->save(self::COMPANY, ['enabled' => false, 'iban' => self::IBAN]);

        app(DemoSeeder::class)->setContainer(app())->__invoke(['companyId' => self::COMPANY]);

        self::assertSame(['enabled' => false, 'iban' => self::IBAN, 'bic' => null, 'payment_terms' => null], $settings->forCompany(self::COMPANY));
    }
}
