<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\CodeList\ArrayCodeLists;
use ISBNLab\Onix\CodeList\BundledCodeLists;
use ISBNLab\Onix\CodeList\EditeurCodeLists;
use ISBNLab\Onix\CodeList\EditeurDownload;
use ISBNLab\Onix\CodeList\NoCodeLists;
use ISBNLab\Onix\Exception\OnixException;
use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\ReaderOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CodeListsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function editeurFiles(): iterable
    {
        yield 'JSON' => [__DIR__ . '/../Fixtures/codelists-sample.json'];
        yield 'XML' => [__DIR__ . '/../Fixtures/codelists-sample.xml'];
    }

    #[DataProvider('editeurFiles')]
    public function testReadsEditeurFormats(string $path): void
    {
        $codeLists = EditeurCodeLists::fromFile($path);

        self::assertSame(99, $codeLists->issue());
        self::assertSame('Test: ISBN-13', $codeLists->label(5, '15'));
        self::assertSame('Test: paperback', $codeLists->label(150, 'BC'));
        self::assertNull($codeLists->label(5, '99'));
        self::assertNull($codeLists->label(999, '01'));
    }

    public function testJsonAndXmlAgree(): void
    {
        self::assertSame(
            EditeurCodeLists::fromFile(__DIR__ . '/../Fixtures/codelists-sample.json')->toArray(),
            EditeurCodeLists::fromFile(__DIR__ . '/../Fixtures/codelists-sample.xml')->toArray(),
        );
    }

    public function testCompiledFileRoundTrip(): void
    {
        $target = tempnam(sys_get_temp_dir(), 'onix') . '.php';
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(\PHP_BINARY),
            escapeshellarg(__DIR__ . '/../../bin/onix-codelists'),
            escapeshellarg(__DIR__ . '/../Fixtures/codelists-sample.json'),
            escapeshellarg($target),
        );
        exec($command, $output, $status);
        self::assertSame(0, $status, implode("\n", $output));

        try {
            $compiled = ArrayCodeLists::fromPhpFile($target);
            self::assertSame(99, $compiled->issue());
            self::assertSame(EditeurCodeLists::fromFile(__DIR__ . '/../Fixtures/codelists-sample.json')->toArray(), $compiled->toArray());
            self::assertStringContainsString('EDItEUR', (string) file_get_contents($target));
        } finally {
            @unlink($target);
        }
    }

    public function testLabelsAreAttachedToCodes(): void
    {
        $options = new ReaderOptions(codeLists: EditeurCodeLists::fromFile(__DIR__ . '/../Fixtures/codelists-sample.json'));

        $onix3 = iterator_to_array(OnixReader::open(__DIR__ . '/../Fixtures/onix30-reference.xml', $options))[1];
        self::assertSame('Test: confirmed', $onix3->notificationType?->label);
        self::assertSame('Test: paperback', $onix3->productForm?->label);
        self::assertSame('Test: ISBN-13', $onix3->identifiers[1]->type?->label);
        self::assertNull($onix3->publishingStatus?->label);

        // ONIX 2.1 product forms (list 7) are labelled from list 150.
        $onix21 = iterator_to_array(OnixReader::open(__DIR__ . '/../Fixtures/onix21-reference.xml', $options))[1];
        self::assertSame(7, $onix21->productForm?->list);
        self::assertSame('Test: hardback', $onix21->productForm->label);
    }

    public function testBundledLabelsByDefault(): void
    {
        $product = iterator_to_array(OnixReader::open(__DIR__ . '/../Fixtures/onix30-reference.xml'))[1];

        self::assertSame('Paperback / softback', $product->productForm?->label);
        self::assertSame('ISBN-13', $product->identifiers[1]->type?->label);
    }

    public function testBundledFileIsEditeurs(): void
    {
        $bundled = new BundledCodeLists();

        self::assertGreaterThanOrEqual(75, $bundled->issue());
        self::assertStringEndsWith('_Issue_' . $bundled->issue() . '.json', BundledCodeLists::path());
        self::assertSame('By (author)', $bundled->label(17, 'A01'));
        self::assertSame('Main content page count', $bundled->label(23, '00'));
        self::assertNull($bundled->label(150, 'not a code'));
        self::assertFileExists(BundledCodeLists::DIRECTORY . '/README.md');
        self::assertStringContainsString('not cover this file', (string) file_get_contents(BundledCodeLists::DIRECTORY . '/README.md'));
    }

    public function testNoCodeListsGivesNoLabels(): void
    {
        $options = new ReaderOptions(codeLists: new NoCodeLists());
        $product = iterator_to_array(OnixReader::open(__DIR__ . '/../Fixtures/onix30-reference.xml', $options))[1];

        self::assertSame('BC', $product->productForm?->value);
        self::assertNull($product->productForm->label);
        self::assertNull((new NoCodeLists())->issue());
    }

    public function testFindsTheNewestIssueOnEditeursPage(): void
    {
        $html = <<<'HTML'
            <a href="/files/ONIX for books - code lists/ONIX_BookProduct_Codelists_Issue_74_Changes.pdf">74</a>
            <a href="/files/ONIX for books - code lists/ONIX_BookProduct_Codelists_Issue_9.json">old</a>
            <a href="/files/ONIX for books - code lists/ONIX_BookProduct_Codelists_Issue_75.json">JSON</a>
            <a href="/files/ONIX for books - code lists/ONIX_BookProduct_Codelists_Issue_75.xml">XML</a>
            <a href="/files/ONIX for books - code lists/ONIX_BookProduct_Codelist_Structure.json">schema</a>
            HTML;

        self::assertSame(
            [75, 'https://www.editeur.org/files/ONIX%20for%20books%20-%20code%20lists/ONIX_BookProduct_Codelists_Issue_75.json'],
            EditeurDownload::latestJsonUrl($html),
        );
    }

    public function testANewIssueWaitsTwoWeeks(): void
    {
        $now = new \DateTimeImmutable('2026-11-30 12:00:00');

        self::assertFalse(EditeurDownload::isSettled(new \DateTimeImmutable('2026-11-20'), $now));
        self::assertTrue(EditeurDownload::isSettled(new \DateTimeImmutable('2026-11-16'), $now));
        self::assertFalse(EditeurDownload::isSettled(null, $now), 'an unknown date is not settled');
        self::assertTrue(EditeurDownload::isSettled(new \DateTimeImmutable('2026-11-29'), $now, 0));
    }

    public function testNoIssueLinkIsAnError(): void
    {
        $this->expectException(OnixException::class);
        EditeurDownload::latestJsonUrl('<p>Page moved</p>');
    }

    public function testRejectsOtherFiles(): void
    {
        $this->expectException(OnixException::class);
        EditeurCodeLists::fromFile(__DIR__ . '/../Fixtures/onix30-reference.xml');
    }
}
