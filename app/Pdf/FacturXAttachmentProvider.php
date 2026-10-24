<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Pdf;

use InvoiceShelf\Modules\Pdf\Contracts\InvoicePdfAttachmentProvider;
use InvoiceShelf\Modules\Pdf\Data\FacturXAttachment;
use Modules\EInvoicing\Application\EInvoiceBuilder;
use Modules\EInvoicing\Application\EInvoicingSettings;

/**
 * Hands the host the CII XML to embed in an invoice's PDF, for a company that
 * switched e-invoicing on and an invoice that meets every requirement.
 * Anything else gets the ordinary PDF.
 */
final class FacturXAttachmentProvider implements InvoicePdfAttachmentProvider
{
    public function __construct(
        private readonly EInvoicingSettings $settings,
        private readonly EInvoiceBuilder $builder,
    ) {}

    public function appliesTo(int $companyId): bool
    {
        return $this->settings->isEnabled($companyId);
    }

    public function attachmentFor(int $companyId, int $invoiceId): ?FacturXAttachment
    {
        $result = $this->builder->build($companyId, $invoiceId);

        return $result?->isValid() ? new FacturXAttachment((string) $result->xml) : null;
    }
}
