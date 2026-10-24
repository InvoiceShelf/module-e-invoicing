<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Application;

use Illuminate\Contracts\Cache\Repository as Cache;
use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use Modules\EInvoicing\Document\En16931Mapper;
use Modules\EInvoicing\Document\Writers\CiiWriter;
use Modules\EInvoicing\Rules\Iban;
use Throwable;

/**
 * Builds an invoice's EN 16931 CII XML, or says what it still needs.
 *
 * The PDF attachment, the XML download and the invoice panel all read this
 * one answer. Checking the requirements is cheap; writing and validating the
 * XML is not, so a written document is cached under a hash of everything it
 * was built from, and any change to the invoice or the settings misses.
 */
final class EInvoiceBuilder
{
    /** Bumped whenever the mapping or the writer changes what they produce. */
    public const OUTPUT_VERSION = '0.1.0';

    public function __construct(
        private readonly InvoiceDocumentReader $reader,
        private readonly EInvoicingSettings $settings,
        private readonly En16931Mapper $mapper,
        private readonly CiiWriter $writer,
        private readonly Cache $cache,
    ) {}

    /** Null when the invoice does not exist in this company. */
    public function build(int $companyId, int $invoiceId): ?BuildResult
    {
        $document = $this->reader->invoiceDocument($companyId, $invoiceId);

        if ($document === null) {
            return null;
        }

        $iban = $this->iban($companyId);
        $bic = $this->settings->bic($companyId);
        $terms = $this->settings->paymentTerms($companyId);

        $mapping = $this->mapper->map($document, $iban, $bic, $terms);

        if ($mapping->invoice === null) {
            return BuildResult::incomplete($mapping->missing);
        }

        $key = 'e-invoicing.cii.'.hash('sha256', json_encode([self::OUTPUT_VERSION, $document->toArray(), $iban, $bic, $terms], JSON_THROW_ON_ERROR));

        try {
            // An empty string records a document that did not validate, so a
            // failure is not rebuilt on every view either.
            $xml = $this->cache->remember($key, now()->addDay(), fn (): string => $this->writer->write($mapping->invoice) ?? '');
        } catch (Throwable $exception) {
            report($exception);
            $xml = '';
        }

        return $xml === '' ? BuildResult::incomplete([Requirement::ValidXml]) : BuildResult::valid($xml);
    }

    /** The settings' IBAN when it is one; an invalid stored value counts as missing. */
    public function iban(int $companyId): ?string
    {
        $iban = $this->settings->iban($companyId);

        return $iban !== null && Iban::isValid($iban) ? $iban : null;
    }
}
