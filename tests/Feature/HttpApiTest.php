<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Feature;

use Modules\EInvoicing\Tests\Support\InvoiceDocuments;
use Modules\EInvoicing\Tests\TestCase;

/** The module's API, through its routes, with the host's authorization faked. */
final class HttpApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->reader->put(InvoiceDocuments::make());
        $this->configureSeller();
    }

    public function test_every_route_sits_behind_the_hosts_company_api_stack(): void
    {
        $routes = collect($this->app['router']->getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/e-invoicing/'));

        self::assertCount(5, $routes);
        foreach ($routes as $route) {
            foreach (['api', 'auth:sanctum', 'company', 'bouncer'] as $middleware) {
                self::assertContains($middleware, $route->middleware(), $route->uri());
            }
        }
    }

    public function test_a_guest_is_turned_away(): void
    {
        $this->getJson('api/v1/e-invoicing/settings', ['company' => '1'])->assertUnauthorized();
    }

    public function test_the_owner_reads_the_settings_with_the_company_readiness(): void
    {
        $this->getJson('api/v1/e-invoicing/settings', $this->member())
            ->assertOk()
            ->assertJson([
                'settings' => ['enabled' => true, 'iban' => self::IBAN, 'bic' => 'BYLADEM1001', 'payment_terms' => null],
                'readiness' => ['capable' => true, 'enabled' => true, 'ready' => true, 'missing' => []],
            ]);

        self::assertContains([1, 1, 'e-invoicing:manage-settings', null], $this->authorization->asked);
    }

    public function test_saving_validates_the_bank_details_on_the_server(): void
    {
        $this->putJson('api/v1/e-invoicing/settings', ['enabled' => true, 'iban' => 'DE03120300000000202051', 'bic' => 'NOPE'], $this->member())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iban', 'bic']);

        $this->putJson('api/v1/e-invoicing/settings', ['enabled' => false, 'iban' => 'at61 1904 3002 3457 3201', 'bic' => '', 'payment_terms' => 'Net 30'], $this->member())
            ->assertOk()
            ->assertJsonPath('settings', ['enabled' => false, 'iban' => 'AT611904300234573201', 'bic' => null, 'payment_terms' => 'Net 30']);
    }

    public function test_only_a_member_who_may_manage_the_settings_reaches_them(): void
    {
        $this->authorization->granted = ['view-invoice'];

        $this->getJson('api/v1/e-invoicing/settings', $this->member())->assertForbidden();
        $this->putJson('api/v1/e-invoicing/settings', ['enabled' => true], $this->member())->assertForbidden();
        $this->getJson('api/v1/e-invoicing/capabilities', $this->member())->assertOk()->assertExactJson(['manage_settings' => false, 'view_invoices' => true]);
    }

    public function test_the_invoice_readiness_follows_the_invoice(): void
    {
        $this->getJson('api/v1/e-invoicing/invoices/1/readiness', $this->member())
            ->assertOk()
            ->assertExactJson(['capable' => true, 'enabled' => true, 'ready' => true, 'fallback' => false, 'missing' => []]);

        $this->getJson('api/v1/e-invoicing/invoices/404/readiness', $this->member())->assertNotFound();
        self::assertContains([1, 1, 'view-invoice', 'invoice'], $this->authorization->asked);
    }

    public function test_a_member_who_may_not_view_invoices_gets_neither_status_nor_xml(): void
    {
        $this->authorization->granted = [];

        $this->getJson('api/v1/e-invoicing/invoices/1/readiness', $this->member())->assertForbidden();
        $this->get('api/v1/e-invoicing/invoices/1/xml', $this->member())->assertForbidden();
    }

    public function test_the_xml_downloads_as_a_file_named_after_the_invoice(): void
    {
        $response = $this->get('api/v1/e-invoicing/invoices/1/xml', $this->member())->assertOk();

        self::assertSame('application/xml', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="INV-000042.xml"', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('CrossIndustryInvoice', (string) $response->getContent());
    }

    /** The XML needs no Gotenberg and no switch, only an invoice that meets the requirements. */
    public function test_the_xml_is_available_with_e_invoicing_off_and_says_what_is_missing_otherwise(): void
    {
        $this->capabilities->capable = false;
        $this->configureSeller(enabled: false);
        $this->get('api/v1/e-invoicing/invoices/1/xml', $this->member())->assertOk();

        $this->configureSeller(enabled: false, iban: null);
        $this->getJson('api/v1/e-invoicing/invoices/1/xml', $this->member())
            ->assertUnprocessable()
            ->assertExactJson(['missing' => ['seller_iban']]);
    }
}
