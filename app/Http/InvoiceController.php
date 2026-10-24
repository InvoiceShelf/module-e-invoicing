<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use Modules\EInvoicing\Application\EInvoiceBuilder;
use Modules\EInvoicing\Application\Readiness;
use Modules\EInvoicing\Support\Authorizes;

/**
 * An invoice's e-invoice status for the invoice panel, and its XML on its own
 * for portals that take a file upload. The XML needs no Gotenberg and no
 * enabled switch: only an invoice that meets the requirements.
 */
final class InvoiceController
{
    public function __construct(
        private readonly Readiness $readiness,
        private readonly EInvoiceBuilder $builder,
        private readonly InvoiceDocumentReader $reader,
        private readonly Authorizes $authorizes,
    ) {}

    public function readiness(Request $request, int $invoice): JsonResponse
    {
        $this->authorizes->requireInvoices($request);

        $report = $this->readiness->forInvoice(Authorizes::companyId($request), $invoice);

        abort_if($report === null, 404);

        return response()->json($report);
    }

    public function xml(Request $request, int $invoice): Response|JsonResponse
    {
        $this->authorizes->requireInvoices($request);
        $companyId = Authorizes::companyId($request);

        $result = $this->builder->build($companyId, $invoice);

        abort_if($result === null, 404);

        if (! $result->isValid()) {
            return response()->json(['missing' => $result->missingKeys()], 422);
        }

        $number = (string) $this->reader->invoiceDocument($companyId, $invoice)?->number;
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number ?: 'invoice').'.xml';

        return response((string) $result->xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
