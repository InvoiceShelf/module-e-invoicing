<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Support;

use DOMDocument;
use DOMXPath;

/** Reads CII XML for assertions, independently of the writer's own verdict. */
final class Cii
{
    public const SETTLEMENT = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement';

    public const AGREEMENT = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement';

    public const DELIVERY = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery';

    public const LINE = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';

    public const SUMMATION = self::SETTLEMENT.'/ram:SpecifiedTradeSettlementHeaderMonetarySummation';

    private const NAMESPACES = [
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
    ];

    /** Valid against the Factur-X EN 16931 schema, checked here rather than trusted. */
    public static function isSchemaValid(string $xml): bool
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        $previous = libxml_use_internal_errors(true);
        $valid = $document->schemaValidate(dirname(__DIR__, 2).'/vendor/horstoeko/zugferd/src/schema/FACTUR-X_EN16931.xsd');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $valid;
    }

    /** @return list<string> */
    public static function values(string $xml, string $expression): array
    {
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);

        foreach (self::NAMESPACES as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        $values = [];
        foreach ($xpath->query($expression) ?: [] as $node) {
            $values[] = trim((string) $node->nodeValue);
        }

        return $values;
    }

    public static function value(string $xml, string $expression): ?string
    {
        return self::values($xml, $expression)[0] ?? null;
    }

    /** @return list<float> */
    public static function amounts(string $xml, string $expression): array
    {
        return array_map(static fn (string $value): float => (float) $value, self::values($xml, $expression));
    }
}
