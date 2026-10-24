<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Unit;

use InvoiceShelf\Modules\Pdf\Data\FacturXAttachment;
use Modules\EInvoicing\Application\Readiness;
use Modules\EInvoicing\Pdf\FacturXAttachmentProvider;
use Modules\EInvoicing\Tests\Support\InvoiceDocuments;
use Modules\EInvoicing\Tests\TestCase;

final class ReadinessAndProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->reader->put(InvoiceDocuments::make());
    }

    public function test_company_readiness_names_the_gaps_the_owner_can_close_whether_or_not_it_is_on(): void
    {
        $this->configureSeller(enabled: false, iban: null);

        self::assertSame(
            ['capable' => true, 'enabled' => false, 'ready' => false, 'missing' => ['seller_iban']],
            app(Readiness::class)->forCompany(self::COMPANY),
        );

        $this->configureSeller();
        self::assertTrue(app(Readiness::class)->forCompany(self::COMPANY)['ready']);
    }

    public function test_nothing_is_built_for_an_invoice_while_e_invoicing_is_off(): void
    {
        $this->configureSeller(enabled: false);

        self::assertSame(
            ['capable' => true, 'enabled' => false, 'ready' => false, 'fallback' => false, 'missing' => []],
            app(Readiness::class)->forInvoice(self::COMPANY, 1),
        );
        self::assertSame(0, $this->reader->reads);
    }

    public function test_an_incomplete_invoice_falls_back_and_says_why(): void
    {
        $this->configureSeller(iban: null);

        self::assertSame(
            ['capable' => true, 'enabled' => true, 'ready' => false, 'fallback' => true, 'missing' => ['seller_iban']],
            app(Readiness::class)->forInvoice(self::COMPANY, 1),
        );
    }

    public function test_a_ready_invoice_still_falls_back_when_the_pdf_service_cannot_embed(): void
    {
        $this->configureSeller();
        self::assertSame(['capable' => true, 'enabled' => true, 'ready' => true, 'fallback' => false, 'missing' => []], app(Readiness::class)->forInvoice(self::COMPANY, 1));

        $this->capabilities->capable = false;
        self::assertSame(['capable' => false, 'enabled' => true, 'ready' => true, 'fallback' => true, 'missing' => []], app(Readiness::class)->forInvoice(self::COMPANY, 1));
    }

    public function test_the_provider_applies_while_e_invoicing_is_on_and_hands_over_the_xml(): void
    {
        $provider = app(FacturXAttachmentProvider::class);

        $this->configureSeller(enabled: false);
        self::assertFalse($provider->appliesTo(self::COMPANY));

        $this->configureSeller();
        self::assertTrue($provider->appliesTo(self::COMPANY));

        $attachment = $provider->attachmentFor(self::COMPANY, 1);
        self::assertInstanceOf(FacturXAttachment::class, $attachment);
        self::assertSame('EN 16931', $attachment->profile);
        self::assertStringContainsString('CrossIndustryInvoice', $attachment->xml);
    }

    public function test_the_provider_hands_over_nothing_for_an_incomplete_or_unknown_invoice(): void
    {
        $this->configureSeller(iban: null);
        $provider = app(FacturXAttachmentProvider::class);

        self::assertNull($provider->attachmentFor(self::COMPANY, 1));
        self::assertNull($provider->attachmentFor(self::COMPANY, 404));
    }
}
