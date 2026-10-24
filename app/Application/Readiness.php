<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Application;

use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;
use Modules\EInvoicing\Document\En16931Mapper;

/**
 * What the settings page and the invoice panel show: whether the PDF service
 * can embed the XML at all, whether the company switched e-invoicing on, and
 * what still stands in the way.
 */
final class Readiness
{
    public function __construct(
        private readonly InvoiceDocumentReader $reader,
        private readonly EInvoicingSettings $settings,
        private readonly En16931Mapper $mapper,
        private readonly EInvoiceBuilder $builder,
        private readonly PdfCapabilities $capabilities,
    ) {}

    /**
     * The company's own gaps, reported whether or not e-invoicing is on: it
     * is what tells an owner the data is complete enough to switch it on.
     *
     * @return array{capable: bool, enabled: bool, ready: bool, missing: list<string>}
     */
    public function forCompany(int $companyId): array
    {
        $missing = $this->mapper->missingSellerRequirements($this->reader->seller($companyId), $this->builder->iban($companyId));

        return [
            'capable' => $this->capabilities->embedsFacturX(),
            'enabled' => $this->settings->isEnabled($companyId),
            'ready' => $missing === [],
            'missing' => array_map(static fn (Requirement $requirement): string => $requirement->value, $missing),
        ];
    }

    /**
     * Whether this invoice's PDF carries the XML, and if not, why. Nothing is
     * built while e-invoicing is off. `fallback` is true when the company
     * issues e-invoices but this invoice goes out as an ordinary PDF.
     *
     * @return array{capable: bool, enabled: bool, ready: bool, fallback: bool, missing: list<string>}|null
     */
    public function forInvoice(int $companyId, int $invoiceId): ?array
    {
        $capable = $this->capabilities->embedsFacturX();

        if (! $this->settings->isEnabled($companyId)) {
            return ['capable' => $capable, 'enabled' => false, 'ready' => false, 'fallback' => false, 'missing' => []];
        }

        $result = $this->builder->build($companyId, $invoiceId);

        if ($result === null) {
            return null;
        }

        return [
            'capable' => $capable,
            'enabled' => true,
            'ready' => $result->isValid(),
            'fallback' => ! $capable || ! $result->isValid(),
            'missing' => $result->missingKeys(),
        ];
    }
}
