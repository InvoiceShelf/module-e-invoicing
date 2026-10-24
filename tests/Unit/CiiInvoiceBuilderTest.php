<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Unit;

use InvalidArgumentException;
use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;
use Modules\EInvoicing\Application\BuildResult;
use Modules\EInvoicing\Application\Requirement;
use Modules\EInvoicing\Document\En16931Mapper;
use Modules\EInvoicing\Document\MappingResult;
use Modules\EInvoicing\Document\Writers\CiiWriter;
use Modules\EInvoicing\Tests\Support\Cii;
use Modules\EInvoicing\Tests\Support\InvoiceDocuments as Docs;
use Modules\EInvoicing\Tests\Support\Scenarios;
use PHPUnit\Framework\TestCase;

/**
 * An invoice becomes EN 16931 CII XML, or the list of requirements it does
 * not meet yet. Every assertion is on that answer: the XML (validated against
 * the Factur-X EN 16931 schema here, then read back through XPath) or the
 * requirement keys.
 *
 * Ported from the builder tests André Kinne and iebl wrote for InvoiceShelf,
 * onto the host's document data instead of its database. Their figures were
 * re-based on the host's worked example; see Support\InvoiceDocuments.
 */
final class CiiInvoiceBuilderTest extends TestCase
{
    private const IBAN = 'DE02120300000000202051';

    /** @return array{0: ?string, 1: list<string>} the XML and the missing requirement keys */
    private function build(InvoiceDocument $document, ?string $iban = self::IBAN, ?string $bic = 'BYLADEM1001', ?string $terms = null): array
    {
        $mapping = (new En16931Mapper)->map($document, $iban, $bic, $terms);

        if ($mapping->invoice === null) {
            return [null, array_map(static fn (Requirement $requirement): string => $requirement->value, $mapping->missing)];
        }

        return [(new CiiWriter)->write($mapping->invoice), []];
    }

    private function xml(InvoiceDocument $document, ?string $terms = null): string
    {
        [$xml, $missing] = $this->build($document, terms: $terms);

        self::assertSame([], $missing);
        self::assertIsString($xml);
        self::assertTrue(Cii::isSchemaValid($xml));

        return $xml;
    }

    /** @return list<string> */
    private function missing(InvoiceDocument $document, ?string $iban = self::IBAN): array
    {
        [$xml, $missing] = $this->build($document, $iban);
        self::assertNull($xml);

        return $missing;
    }

    public function test_a_fully_populated_standard_rated_invoice_yields_schema_valid_en16931_cii_xml(): void
    {
        [$xml, $missing] = $this->build(Docs::make());

        self::assertSame([], $missing);
        self::assertIsString($xml);
        self::assertTrue(Cii::isSchemaValid($xml));
    }

    public function test_the_document_metadata_names_the_invoice_its_type_date_and_currency(): void
    {
        $xml = $this->xml(Docs::make());

        self::assertSame('urn:cen.eu:en16931:2017', Cii::value($xml, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('INV-000042', Cii::value($xml, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID'));
        self::assertSame('380', Cii::value($xml, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame('20260201', Cii::value($xml, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString'));
        self::assertSame('EUR', Cii::value($xml, Cii::SETTLEMENT.'/ram:InvoiceCurrencyCode'));
        self::assertSame('PO-4711', Cii::value($xml, Cii::AGREEMENT.'/ram:BuyerReference'));
    }

    public function test_the_invoice_date_doubles_as_the_delivery_date_bt72(): void
    {
        $xml = $this->xml(Docs::make());

        self::assertSame('20260201', Cii::value($xml, Cii::DELIVERY.'/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString'));
    }

    /**
     * The host stores a credit note with negative amounts and the corrected
     * invoice's number in its reference. It is written as type 381 with
     * positive amounts, refers to that invoice (BG-3) instead of claiming the
     * number as the buyer's reference, and asks for no payment.
     */
    public function test_a_credit_note_is_typed_as_one_with_positive_amounts_and_names_the_invoice_it_corrects(): void
    {
        $document = Scenarios::creditNote();

        [$xml, $missing] = $this->build($document, iban: null);

        self::assertSame([], $missing, 'A credit note asks for no payment, so it needs no IBAN.');
        self::assertTrue(Cii::isSchemaValid((string) $xml));
        self::assertSame('381', Cii::value($xml, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame('INV-000041', Cii::value($xml, Cii::SETTLEMENT.'/ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
        self::assertSame('20260115', Cii::value($xml, Cii::SETTLEMENT.'/ram:InvoiceReferencedDocument/ram:FormattedIssueDateTime/*'));
        self::assertNull(Cii::value($xml, Cii::AGREEMENT.'/ram:BuyerReference'));
        self::assertSame([], Cii::values($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeSettlementPaymentMeans'));
        self::assertSame([100.0, 75.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        self::assertSame([100.0, 150.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
        self::assertSame([47.5], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        self::assertSame([297.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
        self::assertSame([297.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:TotalPrepaidAmount'));
        self::assertSame([0.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:DuePayableAmount'));
    }

    public function test_the_seller_carries_its_name_postal_address_and_tax_registrations(): void
    {
        $xml = $this->xml(Docs::make());
        $seller = Cii::AGREEMENT.'/ram:SellerTradeParty';

        self::assertSame('Acme GmbH', Cii::value($xml, $seller.'/ram:Name'));
        self::assertSame('Marienplatz 1', Cii::value($xml, $seller.'/ram:PostalTradeAddress/ram:LineOne'));
        self::assertSame('München', Cii::value($xml, $seller.'/ram:PostalTradeAddress/ram:CityName'));
        self::assertSame('80331', Cii::value($xml, $seller.'/ram:PostalTradeAddress/ram:PostcodeCode'));
        self::assertSame('DE', Cii::value($xml, $seller.'/ram:PostalTradeAddress/ram:CountryID'));
        self::assertSame(['DE123456789', '143/123/45678'], Cii::values($xml, $seller.'/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function test_the_buyer_carries_its_name_trading_name_country_and_vat_identifier(): void
    {
        $xml = $this->xml(Docs::make());
        $buyer = Cii::AGREEMENT.'/ram:BuyerTradeParty';

        self::assertSame('Muster GmbH', Cii::value($xml, $buyer.'/ram:Name'));
        self::assertSame('Muster GmbH', Cii::value($xml, $buyer.'/ram:SpecifiedLegalOrganization/ram:TradingBusinessName'));
        self::assertSame('Berlin', Cii::value($xml, $buyer.'/ram:PostalTradeAddress/ram:CityName'));
        self::assertSame('DE', Cii::value($xml, $buyer.'/ram:PostalTradeAddress/ram:CountryID'));
        // The customer's tax ID "DE 987 654 321" carries a VAT country prefix.
        self::assertSame(['DE987654321'], Cii::values($xml, $buyer.'/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function test_a_buyer_tax_id_without_a_vat_prefix_is_not_written_as_a_vat_identifier(): void
    {
        $xml = $this->xml(Docs::make(static function (array $d): array {
            $d['buyer']['tax_id'] = '29/815/08151';

            return $d;
        }));

        self::assertSame([], Cii::values($xml, Cii::AGREEMENT.'/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function test_the_bank_details_become_a_sepa_credit_transfer_payment_means(): void
    {
        $xml = $this->xml(Docs::make());
        $means = Cii::SETTLEMENT.'/ram:SpecifiedTradeSettlementPaymentMeans';

        self::assertSame('58', Cii::value($xml, $means.'/ram:TypeCode'));
        self::assertSame(self::IBAN, Cii::value($xml, $means.'/ram:PayeePartyCreditorFinancialAccount/ram:IBANID'));
        self::assertSame('BYLADEM1001', Cii::value($xml, $means.'/ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID'));
        self::assertSame('20260301', Cii::value($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString'));
    }

    public function test_every_line_carries_its_product_quantity_with_unit_code_net_price_and_net_amount(): void
    {
        $xml = $this->xml(Docs::make());

        self::assertSame(['1', '2'], Cii::values($xml, Cii::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'));
        self::assertSame(['Consulting', 'Support'], Cii::values($xml, Cii::LINE.'/ram:SpecifiedTradeProduct/ram:Name'));
        self::assertSame('On site', Cii::value($xml, Cii::LINE.'[1]/ram:SpecifiedTradeProduct/ram:Description'));
        self::assertSame(['HUR', 'C62'], Cii::values($xml, Cii::LINE.'/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode'));
        self::assertSame([1.0, 2.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity'));
        self::assertSame([100.0, 75.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        self::assertSame([100.0, 150.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
    }

    public function test_a_per_invoice_tax_classifies_every_line_and_the_single_vat_breakdown(): void
    {
        $xml = $this->xml(Docs::make());

        self::assertSame(['S', 'S'], Cii::values($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame([19.0, 19.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
        self::assertSame(['S'], Cii::values($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame(['VAT'], Cii::values($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:TypeCode'));
        self::assertSame([250.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:BasisAmount'));
        self::assertSame([47.5], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CalculatedAmount'));
    }

    public function test_the_summation_adds_up_to_the_amounts_the_invoice_stores(): void
    {
        $xml = $this->xml(Docs::make());

        self::assertSame([250.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:LineTotalAmount'));
        self::assertSame([0.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:AllowanceTotalAmount'));
        self::assertSame([250.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxBasisTotalAmount'));
        self::assertSame([47.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxTotalAmount'));
        self::assertSame([297.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
        self::assertSame([0.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TotalPrepaidAmount'));
        self::assertSame([297.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:DuePayableAmount'));
    }

    public function test_a_part_paid_invoice_states_what_was_prepaid_and_what_is_still_due(): void
    {
        $xml = $this->xml(Scenarios::partPaid());

        self::assertSame([297.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
        self::assertSame([200.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TotalPrepaidAmount'));
        self::assertSame([97.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:DuePayableAmount'));
    }

    public function test_the_invoice_notes_travel_as_a_document_note_in_plain_text(): void
    {
        $xml = $this->xml(Docs::make(static fn (array $d): array => array_replace($d, ['notes' => 'Zahlbar ohne Abzug.<br />Rechnung INV-000042.'])));

        $note = (string) Cii::value($xml, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote/ram:Content');

        self::assertStringContainsString('Zahlbar ohne Abzug.', $note);
        self::assertStringContainsString('Rechnung INV-000042.', $note);
        self::assertStringNotContainsString('<br', $note);
    }

    public function test_an_exempt_invoice_carries_category_e_and_its_exemption_reason(): void
    {
        $xml = $this->xml(Scenarios::exempt());

        self::assertSame(['E'], Cii::values($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame(['§ 19 UStG'], Cii::values($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:ExemptionReason'));
        self::assertSame([0.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        self::assertSame(['E', 'E'], Cii::values($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame([0.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxTotalAmount'));
        self::assertSame([250.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
    }

    public function test_per_item_taxes_produce_one_vat_breakdown_per_rate(): void
    {
        $xml = $this->xml(Scenarios::perItemRates());

        self::assertSame([19.0, 7.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
        self::assertSame([19.0, 7.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
        self::assertSame([100.0, 150.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:BasisAmount'));
        self::assertSame([19.0, 10.5], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        self::assertSame([29.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxTotalAmount'));
        self::assertSame([279.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
    }

    public function test_a_document_level_discount_becomes_an_allowance_that_reduces_the_tax_basis(): void
    {
        $xml = $this->xml(Scenarios::documentDiscount());

        self::assertSame(['false'], Cii::values($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator'));
        self::assertSame([50.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:ActualAmount'));
        self::assertSame(['S'], Cii::values($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax/ram:CategoryCode'));
        self::assertSame([200.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:BasisAmount'));
        self::assertSame([250.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:LineTotalAmount'));
        self::assertSame([50.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:AllowanceTotalAmount'));
        self::assertSame([200.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxBasisTotalAmount'));
        self::assertSame([238.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
    }

    public function test_a_line_level_discount_becomes_a_line_allowance(): void
    {
        $xml = $this->xml(Scenarios::lineDiscount());
        $lineAllowance = Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge';

        self::assertSame(['false'], Cii::values($xml, $lineAllowance.'/ram:ChargeIndicator/udt:Indicator'));
        self::assertSame([20.0], Cii::amounts($xml, $lineAllowance.'/ram:ActualAmount'));
        self::assertSame([80.0, 150.0], Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
        self::assertSame([230.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:BasisAmount'));
    }

    public function test_a_missing_iban_is_reported_instead_of_xml(): void
    {
        self::assertSame(['seller_iban'], $this->missing(Docs::make(), iban: null));
    }

    public function test_a_buyer_without_a_country_is_reported_instead_of_xml(): void
    {
        self::assertSame(['buyer_country'], $this->missing(Docs::make(static function (array $d): array {
            $d['buyer']['address']['country_code'] = null;

            return $d;
        })));
    }

    public function test_a_seller_with_only_a_national_tax_number_is_reported_br_co_26_needs_the_vat_identifier(): void
    {
        self::assertSame(['seller_tax_registration'], $this->missing(Docs::make(static function (array $d): array {
            $d['seller']['vat_id'] = null;

            return $d;
        })));
    }

    public function test_several_gaps_are_reported_together_each_named_once(): void
    {
        self::assertSame(
            ['seller_postcode', 'seller_tax_registration', 'seller_iban', 'buyer_country'],
            $this->missing(Docs::make(static function (array $d): array {
                $d['seller']['vat_id'] = null;
                $d['seller']['tax_id'] = null;
                $d['seller']['address']['zip'] = null;
                $d['buyer']['address']['country_code'] = null;

                return $d;
            }), iban: null),
        );
    }

    public function test_an_invoice_without_any_tax_has_no_vat_breakdown_to_write(): void
    {
        self::assertSame(['invoice_tax'], $this->missing(Docs::make(Scenarios::documentTaxes([], 0, 25000))));
    }

    public function test_an_invoice_charging_two_taxes_at_once_cannot_classify_its_lines(): void
    {
        self::assertSame(['single_tax_rate_per_line'], $this->missing(Docs::make(Scenarios::documentTaxes([Docs::tax(4750), Docs::tax(1750, '7')], 6500, 31500))));
    }

    public function test_tax_inclusive_prices_are_reported_rather_than_reinterpreted_as_net(): void
    {
        self::assertSame(['net_prices'], $this->missing(Docs::make(static fn (array $d): array => array_replace($d, ['tax_included' => true]))));
    }

    public function test_an_exempt_tax_without_an_exemption_reason_is_reported(): void
    {
        self::assertSame(['tax_exemption_reason'], $this->missing(Docs::make(Scenarios::documentTaxes([Docs::tax(0, '0', 'E')], 0, 25000))));
    }

    public function test_a_fixed_amount_tax_carries_no_rate_a_vat_breakdown_could_state(): void
    {
        $fixed = ['calculation_type' => 'fixed', 'percent' => null, 'fixed_amount' => 500] + Docs::tax(500);

        self::assertSame(['tax_rate_percentage'], $this->missing(Docs::make(Scenarios::documentTaxes([$fixed], 500, 25500))));
    }

    public function test_an_invoice_without_line_items_is_reported(): void
    {
        self::assertSame(['line_items'], $this->missing(Docs::make(static fn (array $d): array => array_replace($d, ['lines' => []]))));
    }

    public function test_an_unnamed_line_item_is_reported(): void
    {
        self::assertSame(['line_item_name'], $this->missing(Docs::make(static function (array $d): array {
            $d['lines'][0]['name'] = '';

            return $d;
        })));
    }

    public function test_a_result_is_either_xml_or_requirements_never_both_and_never_neither(): void
    {
        $valid = BuildResult::valid('<xml/>');
        self::assertTrue($valid->isValid());
        self::assertSame('<xml/>', $valid->xml);
        self::assertSame([], $valid->missing);

        $incomplete = BuildResult::incomplete([Requirement::SellerIban]);
        self::assertFalse($incomplete->isValid());
        self::assertNull($incomplete->xml);
        self::assertSame(['seller_iban'], $incomplete->missingKeys());

        foreach ([static fn () => BuildResult::incomplete([]), static fn () => MappingResult::incomplete([])] as $empty) {
            try {
                $empty();
                self::fail('An incomplete result without a requirement was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_a_deducted_down_payment_becomes_a_document_allowance_instead_of_a_negative_line(): void
    {
        $xml = $this->xml(Scenarios::downPayment());

        self::assertSame(['1', '2'], Cii::values($xml, Cii::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'));
        foreach (Cii::amounts($xml, Cii::LINE.'/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount') as $price) {
            self::assertGreaterThanOrEqual(0, $price);
        }
        self::assertSame([100.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:ActualAmount'));
        self::assertSame(['Anzahlung'], Cii::values($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:Reason'));
        self::assertSame(['S'], Cii::values($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax/ram:CategoryCode'));
        self::assertSame([150.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:BasisAmount'));
        self::assertSame([28.5], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        self::assertSame([250.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:LineTotalAmount'));
        self::assertSame([100.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:AllowanceTotalAmount'));
        self::assertSame([150.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxBasisTotalAmount'));
        self::assertSame([178.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:GrandTotalAmount'));
        self::assertSame([178.5], Cii::amounts($xml, Cii::SUMMATION.'/ram:DuePayableAmount'));
    }

    /**
     * With a tax per line, the host charges each line's tax on its share of the
     * document discount (DocumentTaxes::lineBase), so the breakdown adds up.
     */
    public function test_a_deduction_and_a_document_discount_both_reduce_the_tax_basis(): void
    {
        $xml = $this->xml(Scenarios::deductionAndDiscount());

        self::assertSame([100.0, 50.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradeAllowanceCharge/ram:ActualAmount'));
        self::assertSame([100.0], Cii::amounts($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:BasisAmount'));
        self::assertSame([150.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:AllowanceTotalAmount'));
        self::assertSame([100.0], Cii::amounts($xml, Cii::SUMMATION.'/ram:TaxBasisTotalAmount'));
    }

    public function test_an_invoice_made_only_of_deductions_has_no_line_to_state(): void
    {
        $missing = $this->missing(Docs::make(static fn (array $d): array => array_replace($d, [
            'lines' => [Docs::line(1, 'Anzahlung', -10000, '1', -10000)],
        ])));

        self::assertContains('line_items', $missing);
    }

    public function test_a_negative_net_price_on_a_positive_amount_is_reported_br_27(): void
    {
        self::assertSame(['negative_price'], $this->missing(Docs::make(static function (array $d): array {
            $d['lines'][0] = array_replace($d['lines'][0], ['price' => -10000, 'quantity' => '-1', 'total' => 10000]);

            return $d;
        })));
    }

    public function test_a_standard_rated_tax_at_zero_percent_is_reported_br_s_05(): void
    {
        self::assertSame(['tax_rate_for_category'], $this->missing(Docs::make(Scenarios::documentTaxes([Docs::tax(0, '0')], 0, 25000))));
    }

    public function test_a_zero_rated_tax_with_a_rate_above_zero_is_reported_br_z_05(): void
    {
        self::assertSame(['tax_rate_for_category'], $this->missing(Docs::make(Scenarios::documentTaxes([Docs::tax(4750, '19', 'Z')], 4750, 29750))));
    }

    /** BR-CO-25: an amount due needs a due date or payment terms. */
    public function test_an_invoice_without_a_due_date_needs_payment_terms(): void
    {
        $document = Scenarios::withoutDueDate();

        self::assertSame(['payment_terms'], $this->missing($document));

        $xml = $this->xml($document, terms: 'Payable within 14 days without deduction.');

        self::assertSame('Payable within 14 days without deduction.', Cii::value($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradePaymentTerms/ram:Description'));
        self::assertNull(Cii::value($xml, Cii::SETTLEMENT.'/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString'));
    }

    public function test_a_fully_paid_invoice_without_a_due_date_needs_no_payment_terms(): void
    {
        $this->xml(Docs::make(static fn (array $d): array => array_replace($d, ['due_date' => null, 'due_amount' => 0])));
    }

    /** BR-AE-02: reverse charge names the buyer's VAT identifier. */
    public function test_reverse_charge_needs_the_buyer_vat_identifier(): void
    {
        $reverseCharge = Scenarios::documentTaxes([Docs::tax(0, '0', 'AE', 'Reverse charge')], 0, 25000);

        $xml = $this->xml(Docs::make($reverseCharge));
        self::assertSame(['AE'], Cii::values($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame(['DE987654321'], Cii::values($xml, Cii::AGREEMENT.'/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));

        self::assertSame(['buyer_vat_id'], $this->missing(Docs::make($reverseCharge, static function (array $d): array {
            $d['buyer']['tax_id'] = null;

            return $d;
        })));
    }

    /** BR-IC-12: an intra-community supply names the country the goods went to. */
    public function test_an_intra_community_supply_names_the_deliver_to_country(): void
    {
        $xml = $this->xml(Scenarios::intraCommunity());

        self::assertSame(['K'], Cii::values($xml, Cii::SETTLEMENT.'/ram:ApplicableTradeTax/ram:CategoryCode'));
        self::assertSame('FR', Cii::value($xml, Cii::DELIVERY.'/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CountryID'));
        self::assertSame(['FR12345678901'], Cii::values($xml, Cii::AGREEMENT.'/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function test_categories_this_version_does_not_write_are_reported(): void
    {
        foreach (['O', 'L', 'M'] as $category) {
            self::assertSame(
                ['unsupported_tax_category'],
                $this->missing(Docs::make(Scenarios::documentTaxes([Docs::tax(0, '0', $category, 'Not subject to VAT')], 0, 25000))),
                "Category {$category}",
            );
        }
    }
}
