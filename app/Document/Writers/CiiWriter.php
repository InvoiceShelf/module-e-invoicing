<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Document\Writers;

use DateTimeImmutable;
use DOMDocument;
use horstoeko\zugferd\codelists\ZugferdAllowanceCodes;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdXsdValidator;
use InvoiceShelf\Modules\Documents\Data\PostalAddress;
use Modules\EInvoicing\Document\En16931Invoice;

/**
 * Writes an EN 16931 invoice as UN/CEFACT CII XML in the EN 16931 profile,
 * the syntax ZUGFeRD and Factur-X embed in a PDF, with horstoeko/zugferd.
 *
 * The result is XSD-validated and parsed back before it is handed out, so
 * "valid" means the bytes returned are the ones that were checked.
 */
final class CiiWriter
{
    /** The XML, or null when it did not validate. */
    public function write(En16931Invoice $invoice): ?string
    {
        $document = $this->compose($invoice);

        if ((new ZugferdXsdValidator($document))->validate()->hasValidationErrors()) {
            return null;
        }

        $xml = $document->getContent();

        return $this->isWellFormed($xml) ? $xml : null;
    }

    private function compose(En16931Invoice $invoice): ZugferdDocumentBuilder
    {
        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

        $document->setDocumentInformation(
            $invoice->number,
            $invoice->typeCode === En16931Invoice::TYPE_CREDIT_NOTE ? ZugferdInvoiceType::CREDITNOTE : ZugferdInvoiceType::INVOICE,
            $this->date($invoice->issueDate),
            $invoice->currencyCode,
        );

        // The invoice carries no delivery date of its own, but the delivery
        // block must not stay empty: Peppol rejects empty elements
        // (PEPPOL-EN16931-R008) and German UStG § 14 expects a date of supply.
        // Until the data can state one, the issue date is the delivery date
        // (BT-72), the reading recipients apply to an invoice that names none.
        $document->setDocumentSupplyChainEvent($this->date($invoice->issueDate));

        if ($invoice->buyerReference !== null) {
            $document->setDocumentBuyerReference($invoice->buyerReference);
        }

        if ($invoice->note !== null) {
            $document->addDocumentNote($invoice->note);
        }

        if ($invoice->precedingInvoice !== null) {
            $document->setDocumentInvoiceReferencedDocument(
                $invoice->precedingInvoice->number,
                null,
                $invoice->precedingInvoice->date ? $this->date($invoice->precedingInvoice->date) : null,
            );
        }

        $this->writeParties($document, $invoice);
        $this->writePayment($document, $invoice);
        $this->writeLines($document, $invoice);
        $this->writeTotals($document, $invoice);

        return $document;
    }

    private function writeParties(ZugferdDocumentBuilder $document, En16931Invoice $invoice): void
    {
        $seller = $invoice->seller;
        $document->setDocumentSeller($seller->name);
        $document->setDocumentSellerAddress(...$this->postalAddress($seller->address));

        if ($seller->vatId !== null) {
            $document->addDocumentSellerVATRegistrationNumber($seller->vatId);
        }

        if ($seller->taxId !== null) {
            $document->addDocumentSellerTaxNumber($seller->taxId);
        }

        $buyer = $invoice->buyer;
        $document->setDocumentBuyer($buyer->name);

        if ($buyer->companyName !== null && $buyer->companyName !== '') {
            $document->setDocumentBuyerLegalOrganisation(null, null, $buyer->companyName);
        }

        $document->setDocumentBuyerAddress(...$this->postalAddress($buyer->address));

        if ($invoice->buyerVatId !== null) {
            $document->addDocumentBuyerVATRegistrationNumber($invoice->buyerVatId);
        }

        if ($invoice->deliverTo !== null) {
            $document->setDocumentShipTo($buyer->name);
            $document->setDocumentShipToAddress(...$this->postalAddress($invoice->deliverTo));
        }
    }

    /**
     * The credit transfer (BG-16) and the payment terms. BG-17 identifies the
     * bank by its BIC alone; no bank name belongs in the XML.
     */
    private function writePayment(ZugferdDocumentBuilder $document, En16931Invoice $invoice): void
    {
        if ($invoice->iban !== null) {
            $document->addDocumentPaymentMeanToCreditTransfer($invoice->iban, $invoice->seller->name, null, $invoice->bic);
        }

        if ($invoice->dueDate !== null || $invoice->paymentTerms !== null) {
            $document->addDocumentPaymentTerm(
                $invoice->paymentTerms,
                $invoice->dueDate !== null ? $this->date($invoice->dueDate) : null,
            );
        }
    }

    private function writeLines(ZugferdDocumentBuilder $document, En16931Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            $document->addNewPosition($line->id);
            $document->setDocumentPositionProductDetails($line->name, $line->description);
            $document->setDocumentPositionNetPrice($this->amount($line->netPrice));
            $document->setDocumentPositionQuantity($line->quantity, $line->unitCode);
            $document->addDocumentPositionTax($line->categoryCode, ZugferdVatTypeCodes::VALUE_ADDED_TAX, $line->rate);

            if ($line->allowance > 0) {
                $document->addDocumentPositionAllowanceCharge(
                    $this->amount($line->allowance), false, null, null, ZugferdAllowanceCodes::DISCOUNT, 'Discount',
                );
            }

            $document->setDocumentPositionLineSummation($this->amount($line->netAmount));
        }
    }

    private function writeTotals(ZugferdDocumentBuilder $document, En16931Invoice $invoice): void
    {
        foreach ($invoice->vatBreakdown as $group) {
            $document->addDocumentTax(
                $group->categoryCode,
                ZugferdVatTypeCodes::VALUE_ADDED_TAX,
                $this->amount($group->basis),
                $this->amount($group->tax),
                $group->rate,
                $group->exemptionReason,
            );
        }

        foreach ($invoice->allowances as $allowance) {
            $document->addDocumentAllowanceCharge(
                $this->amount($allowance->amount),
                false,
                $allowance->categoryCode,
                ZugferdVatTypeCodes::VALUE_ADDED_TAX,
                $allowance->rate,
                null, null, null, null, null,
                ZugferdAllowanceCodes::DISCOUNT,
                $allowance->reason,
            );
        }

        $totals = $invoice->totals;
        $document->setDocumentSummation(
            $this->amount($totals->grandTotal),
            $this->amount($totals->duePayable),
            $this->amount($totals->lineTotal),
            $this->amount(0),
            $this->amount($totals->allowanceTotal),
            $this->amount($totals->taxBasis),
            $this->amount($totals->taxTotal),
            null,
            $this->amount($totals->prepaid),
        );
    }

    /**
     * An address as a CII postal address takes it: street, second line, third
     * line, post code, city, country code, subdivision.
     *
     * @return array{?string, ?string, null, ?string, ?string, ?string, ?string}
     */
    private function postalAddress(?PostalAddress $address): array
    {
        return [
            $address?->street1,
            $address?->street2,
            null,
            $address?->zip,
            $address?->city,
            $address?->countryCode,
            $address?->state,
        ];
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    /** Hundredths as the decimal amount EN 16931 states. */
    private function amount(int $hundredths): float
    {
        return round($hundredths / 100, 2);
    }

    private function isWellFormed(string $xml): bool
    {
        if ($xml === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $wellFormed = (new DOMDocument)->loadXML($xml) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $wellFormed;
    }
}
