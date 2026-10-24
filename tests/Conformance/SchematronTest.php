<?php

declare(strict_types=1);

namespace Modules\EInvoicing\Tests\Conformance;

use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;
use Modules\EInvoicing\Document\En16931Mapper;
use Modules\EInvoicing\Document\Writers\CiiWriter;
use Modules\EInvoicing\Tests\Support\Scenarios;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Every invoice that must become an e-invoice, judged twice: by Mustang CLI
 * (the Factur-X EN 16931 XSD and schematron) and by the CEN EN 16931 CII
 * schematron itself, which carries business rules the Factur-X one does not,
 * BR-CO-25 among them. The CEN rules are the XSLT Mustang ships, run through
 * the Saxon inside the same jar.
 *
 * Needs Java and Mustang, named by MUSTANG_JAR, so the group is excluded from
 * ordinary runs; CI runs it with `vendor/bin/phpunit --group schematron`.
 */
#[Group('schematron')]
final class SchematronTest extends TestCase
{
    /** @return array<string, array{InvoiceDocument, ?string}> */
    public static function scenarios(): array
    {
        return Scenarios::valid();
    }

    #[DataProvider('scenarios')]
    public function test_the_xml_passes_the_en16931_business_rules(InvoiceDocument $document, ?string $terms): void
    {
        $mapping = (new En16931Mapper)->map($document, 'DE02120300000000202051', 'BYLADEM1001', $terms);
        self::assertNotNull($mapping->invoice, implode(', ', array_map(static fn ($r) => $r->value, $mapping->missing)));

        $xml = (new CiiWriter)->write($mapping->invoice);
        self::assertIsString($xml);

        $file = tempnam(sys_get_temp_dir(), 'einv').'.xml';
        file_put_contents($file, $xml);

        try {
            [$status, $report] = $this->mustang($file);
            $failed = $this->cenFatalFailures($file);
        } finally {
            @unlink($file);
        }

        self::assertSame('valid', $status, $report);
        self::assertSame([], $failed, 'The CEN EN 16931 rules failed: '.implode(', ', $failed));
    }

    /**
     * The fatal rules the CEN EN 16931 CII schematron reports as failed.
     *
     * @return list<string>
     */
    private function cenFatalFailures(string $file): array
    {
        $jar = (string) realpath((string) getenv('MUSTANG_JAR'));
        $process = new Process([
            getenv('JAVA_BIN') ?: 'java', '-cp', $jar, 'net.sf.saxon.Transform',
            '-s:'.$file,
            '-xsl:jar:file:'.$jar.'!/xslt/en16931schematron/EN16931-CII-validation.xslt',
        ], timeout: 300);
        $process->run();

        $svrl = new \DOMDocument;
        if (trim($process->getOutput()) === '' || ! $svrl->loadXML($process->getOutput())) {
            throw new RuntimeException("The CEN schematron produced no report:\n".$process->getErrorOutput());
        }

        $xpath = new \DOMXPath($svrl);
        $xpath->registerNamespace('svrl', 'http://purl.oclc.org/dsdl/svrl');

        $failed = [];
        foreach ($xpath->query("//svrl:failed-assert[@flag='fatal']") ?: [] as $node) {
            $failed[] = $node instanceof \DOMElement ? $node->getAttribute('id') : '?';
        }

        return $failed;
    }

    /** The check itself: XML that breaks BR-CO-25 must be caught, or a green run proves nothing. */
    public function test_the_cen_rules_catch_an_invoice_that_breaks_one(): void
    {
        $mapping = (new En16931Mapper)->map(Scenarios::standard(), 'DE02120300000000202051', 'BYLADEM1001', null);
        $xml = (string) (new CiiWriter)->write($mapping->invoice);
        $broken = (string) preg_replace('#<ram:SpecifiedTradePaymentTerms>.*?</ram:SpecifiedTradePaymentTerms>#s', '', $xml);

        $file = tempnam(sys_get_temp_dir(), 'einv').'.xml';
        file_put_contents($file, $broken);

        try {
            self::assertContains('BR-CO-25', $this->cenFatalFailures($file));
        } finally {
            @unlink($file);
        }
    }

    /** @return array{string, string} the overall status and the report */
    private function mustang(string $file): array
    {
        $jar = getenv('MUSTANG_JAR');

        if (! is_string($jar) || ! is_file($jar)) {
            throw new RuntimeException('MUSTANG_JAR must name Mustang-CLI-<version>.jar (https://github.com/ZUGFeRD/mustangproject/releases).');
        }

        $process = new Process([getenv('JAVA_BIN') ?: 'java', '-jar', $jar, '--action', 'validate', '--source', $file], timeout: 300);
        $process->run();
        $output = $process->getOutput();

        if (preg_match('/<validation.*<\/validation>/s', $output, $match) !== 1) {
            throw new RuntimeException("Mustang produced no report:\n".$output.$process->getErrorOutput());
        }

        // The last summary closes the whole report; the ones before it close sections.
        preg_match_all('/<summary status="([a-z]+)"\/>/', $match[0], $statuses);

        return [end($statuses[1]) ?: 'none', $match[0]];
    }
}
