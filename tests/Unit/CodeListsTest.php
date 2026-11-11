<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\CodeList\ArrayCodeLists;
use ISBNLab\Onix\CodeList\EditeurCodeLists;
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

    public function testNoLabelsByDefault(): void
    {
        $product = iterator_to_array(OnixReader::open(__DIR__ . '/../Fixtures/onix30-reference.xml'))[1];
        self::assertNull($product->productForm?->label);
        self::assertNull((new NoCodeLists())->issue());
    }

    public function testRejectsOtherFiles(): void
    {
        $this->expectException(OnixException::class);
        EditeurCodeLists::fromFile(__DIR__ . '/../Fixtures/onix30-reference.xml');
    }
}
