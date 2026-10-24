<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Unit;

use Modules\EInvoicing\Application\EInvoiceBuilder;
use Modules\EInvoicing\Tests\Support\InvoiceDocuments;
use Modules\EInvoicing\Tests\TestCase;

/** The orchestration around the mapper and the writer: settings, lookups and the cache. */
final class EInvoiceBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->reader->put(InvoiceDocuments::make());
        $this->configureSeller();
    }

    public function test_an_invoice_with_everything_builds_to_xml(): void
    {
        $result = app(EInvoiceBuilder::class)->build(self::COMPANY, 1);

        self::assertTrue($result?->isValid());
        self::assertStringContainsString('<ram:ID>INV-000042</ram:ID>', (string) $result->xml);
    }

    public function test_an_invoice_of_another_company_or_none_at_all_reads_as_missing(): void
    {
        self::assertNull(app(EInvoiceBuilder::class)->build(2, 1));
        self::assertNull(app(EInvoiceBuilder::class)->build(self::COMPANY, 404));
    }

    public function test_a_stored_value_that_is_not_an_iban_counts_as_no_iban(): void
    {
        $this->settings->putCompany(self::COMPANY, 'module.e-invoicing.iban', 'Sparkasse, Konto 12345');

        self::assertSame(['seller_iban'], app(EInvoiceBuilder::class)->build(self::COMPANY, 1)?->missingKeys());
    }

    public function test_the_payment_terms_setting_stands_in_for_a_missing_due_date(): void
    {
        $this->reader->put(InvoiceDocuments::make(static fn (array $d): array => array_replace($d, ['due_date' => null])));
        self::assertSame(['payment_terms'], app(EInvoiceBuilder::class)->build(self::COMPANY, 1)?->missingKeys());

        $this->settings->putCompany(self::COMPANY, 'module.e-invoicing.payment_terms', 'Net 14 days');
        self::assertTrue(app(EInvoiceBuilder::class)->build(self::COMPANY, 1)?->isValid());
    }

    public function test_a_written_document_is_cached_until_the_invoice_or_the_settings_change(): void
    {
        $builder = app(EInvoiceBuilder::class);
        $first = $builder->build(self::COMPANY, 1)?->xml;

        self::assertSame($first, $builder->build(self::COMPANY, 1)?->xml);
        self::assertCount(1, $this->cachedDocuments());

        $this->settings->putCompany(self::COMPANY, 'module.e-invoicing.bic', 'COBADEFFXXX');
        $second = $builder->build(self::COMPANY, 1)?->xml;

        self::assertNotSame($first, $second);
        self::assertStringContainsString('COBADEFFXXX', (string) $second);
        self::assertCount(2, $this->cachedDocuments());
    }

    /** @return list<string> */
    private function cachedDocuments(): array
    {
        $store = cache()->store()->getStore();
        $storage = (fn () => $this->storage)->call($store);

        return array_values(array_filter(array_keys($storage), static fn (string $key): bool => str_starts_with($key, 'e-invoicing.cii.')));
    }
}
