<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Application;

/**
 * One thing an invoice still needs before it can become an e-invoice.
 *
 * The mapper reports these instead of XML, and the same list feeds the
 * company readiness check, the invoice panel and the decision to fall back to
 * the ordinary PDF. Each case names a single input as precisely as the data
 * allows, so a user can act on it without reading the XML.
 *
 * The values are stable identifiers: the frontend translates them and tests
 * assert on them, so they must not change once released.
 */
enum Requirement: string
{
    /** BT-1, the invoice number. */
    case InvoiceNumber = 'invoice_number';

    /** BT-2, the issue date. */
    case InvoiceDate = 'invoice_date';

    /** BT-5, the ISO 4217 currency code. */
    case InvoiceCurrency = 'invoice_currency';

    /** BT-27, the seller's name. */
    case SellerName = 'seller_name';

    /** BT-35, the first line of the seller's postal address. */
    case SellerStreet = 'seller_street';

    /** BT-38, the seller's post code. */
    case SellerPostcode = 'seller_postcode';

    /** BT-37, the seller's city. */
    case SellerCity = 'seller_city';

    /** BT-40, the seller's country. */
    case SellerCountry = 'seller_country';

    /**
     * BT-31, the seller's VAT identifier. A national tax number alone is only
     * BT-32, which BR-CO-26 does not accept as identification.
     */
    case SellerTaxRegistration = 'seller_tax_registration';

    /** BT-84, the IBAN payments go to, from the module settings. Not asked of credit notes. */
    case SellerIban = 'seller_iban';

    /** BT-44, the buyer's name. */
    case BuyerName = 'buyer_name';

    /** BT-55, the country of the buyer's billing address. */
    case BuyerCountry = 'buyer_country';

    /**
     * BT-48, the buyer's VAT identifier, which reverse charge (AE) and
     * intra-community supply (K) require (BR-AE-02, BR-IC-02). Read from the
     * customer's tax ID when it is a VAT identifier with its country prefix.
     */
    case BuyerVatId = 'buyer_vat_id';

    /** BG-25, at least one invoice line. */
    case LineItems = 'line_items';

    /** BT-153, a name on every line. */
    case LineItemName = 'line_item_name';

    /** EN 16931 states amounts net of VAT; tax-inclusive prices have no net breakdown. */
    case NetPrices = 'net_prices';

    /** BT-118, a tax whose tax type, and with it its category code, is gone. */
    case TaxCategoryCode = 'tax_category_code';

    /** BT-119, a fixed-amount tax has no rate a VAT breakdown could state. */
    case TaxRatePercentage = 'tax_rate_percentage';

    /** BG-23, at least one tax, so there is a VAT breakdown to write. */
    case InvoiceTax = 'invoice_tax';

    /** BG-30, one VAT category and rate per line, never several. */
    case SingleTaxRatePerLine = 'single_tax_rate_per_line';

    /** BT-120, an exemption reason on the exempt categories, e.g. § 19 UStG. */
    case TaxExemptionReason = 'tax_exemption_reason';

    /** BR-27, a net price is never negative; deductions are lines with a negative amount. */
    case NegativePrice = 'negative_price';

    /** BR-S-05, BR-Z-05, BR-E-05 and siblings: the rate has to fit the category. */
    case TaxRateForCategory = 'tax_rate_for_category';

    /**
     * Categories this version does not write: outside the scope of tax (O),
     * which forbids the seller VAT identifier every other invoice needs, and
     * the Canary Islands (L) and Ceuta and Melilla (M) taxes.
     */
    case UnsupportedTaxCategory = 'unsupported_tax_category';

    /** BR-CO-25, an amount due needs a due date or payment terms (module settings). */
    case PaymentTerms = 'payment_terms';

    /**
     * The composed document failed XSD validation or could not be composed.
     * Never reported together with the data requirements above: it is the
     * last gate, so invalid XML is never handed out.
     */
    case ValidXml = 'valid_xml';

    /** Whether the company can close this gap in its own settings, the readiness check's subset. */
    public function isSellerSide(): bool
    {
        return in_array($this, [
            self::SellerName, self::SellerStreet, self::SellerPostcode, self::SellerCity,
            self::SellerCountry, self::SellerTaxRegistration, self::SellerIban,
        ], true);
    }
}
