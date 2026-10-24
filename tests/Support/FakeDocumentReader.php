<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use InvoiceShelf\Modules\Documents\Data\DocumentParty;
use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;

/** Serves the documents a test put in, for company 1 only. */
final class FakeDocumentReader implements InvoiceDocumentReader
{
    /** @var array<int, InvoiceDocument> */
    public array $documents = [];

    public int $reads = 0;

    public function put(InvoiceDocument $document): void
    {
        $this->documents[$document->id] = $document;
    }

    public function invoiceDocument(int $companyId, int $invoiceId): ?InvoiceDocument
    {
        $this->reads++;
        $document = $this->documents[$invoiceId] ?? null;

        return $document !== null && $document->companyId === $companyId ? $document : null;
    }

    public function seller(int $companyId): ?DocumentParty
    {
        return InvoiceDocuments::make()->seller;
    }
}
