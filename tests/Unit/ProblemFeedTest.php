<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\Exception\InvalidProductException;
use ISBNLab\Onix\Exception\MalformedXmlException;
use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\ProductError;
use ISBNLab\Onix\ReaderOptions;
use PHPUnit\Framework\TestCase;

final class ProblemFeedTest extends TestCase
{
    private const PROBLEMS = __DIR__ . '/../Fixtures/onix30-problems.xml';

    public function testBadProductsAreSkippedAndReported(): void
    {
        $reported = [];
        $reader = OnixReader::open(self::PROBLEMS, new ReaderOptions(onError: static function (ProductError $error) use (&$reported): void {
            $reported[] = $error;
        }));

        $products = iterator_to_array($reader);

        self::assertSame([1, 3, 4], array_keys($products));
        self::assertSame(1, $reader->errorCount());
        $error = $reader->errors()[0];
        self::assertSame(2, $error->index);
        self::assertNull($error->recordReference);
        self::assertSame('Product has no RecordReference and no ProductIdentifier', $error->message);
        self::assertStringContainsString('Nobody knows which book this is', $error->xml);
        self::assertEquals([$error], $reported);
    }

    public function testMessyValuesAreReadWhereTheyCanBeAndWarnedAboutWhereTheyCannot(): void
    {
        $product = iterator_to_array(OnixReader::open(self::PROBLEMS))[3];

        self::assertSame('messy-3', $product->recordReference);
        self::assertSame('03', $product->notificationType?->value);
        self::assertSame('9780306406164', $product->isbn13());
        self::assertNull($product->gtin13(), 'an empty IDValue is no identifier');
        self::assertNull($product->productForm, 'an empty element is no value');
        self::assertSame('Messy   Title', $product->title());
        self::assertSame('A. Writer', $product->authors()[0]->displayName());
        self::assertNull($product->authors()[0]->sequenceNumber);
        self::assertNull($product->editionNumber);
        self::assertSame(1024, $product->pageCount());
        self::assertSame('2024-02-30', $product->publicationDate()?->value);
        self::assertNull($product->publicationDate()->toDateTime(), 'an impossible date is not converted');
        self::assertNull($product->price('USD'));
        self::assertSame(1299.0, $product->price('JPY')?->amount);
        self::assertSame([
            'Contributor SequenceNumber "first" is not a number',
            'EditionNumber "2nd" is not a number',
            'PriceAmount "abc" is not a number',
        ], $product->warnings);
    }

    public function testThrowOnErrorStopsAtTheFirstBadProduct(): void
    {
        $reader = OnixReader::open(self::PROBLEMS, new ReaderOptions(throwOnError: true));
        $seen = [];

        try {
            foreach ($reader as $index => $product) {
                $seen[] = $index;
            }
            self::fail('Expected an exception');
        } catch (InvalidProductException $e) {
            self::assertSame(2, $e->error?->index);
        }
        self::assertSame([1], $seen);
    }

    public function testTruncatedFileEndsWithMalformedXmlAfterTheGoodProducts(): void
    {
        $reader = OnixReader::open(__DIR__ . '/../Fixtures/onix30-truncated.xml');
        $seen = [];

        try {
            foreach ($reader as $index => $product) {
                $seen[] = $product->recordReference;
            }
            self::fail('Expected an exception');
        } catch (MalformedXmlException $e) {
            self::assertGreaterThan(0, $e->xmlLine);
            self::assertStringContainsString('Malformed XML', $e->getMessage());
        }
        self::assertSame(['ok-1'], $seen);
    }

    public function testErrorStorageIsCappedButCountIsExact(): void
    {
        $products = str_repeat('<Product><NotificationType>03</NotificationType></Product>', 5);
        $reader = OnixReader::fromString('<ONIXMessage release="3.0">' . $products . '</ONIXMessage>', new ReaderOptions(maxStoredErrors: 2));

        self::assertSame([], iterator_to_array($reader));
        self::assertSame(5, $reader->errorCount());
        self::assertCount(2, $reader->errors());
    }

    public function testUnknownNamedEntitiesSurviveAsText(): void
    {
        $xml = '<ONIXMessage release="3.0"><Product><RecordReference>r&nosuchentity;1</RecordReference></Product></ONIXMessage>';
        $products = iterator_to_array(OnixReader::fromString($xml));

        self::assertSame('r&nosuchentity;1', $products[1]->recordReference);
    }

    public function testMissingFileIsReported(): void
    {
        $this->expectException(\ISBNLab\Onix\Exception\OnixException::class);
        OnixReader::open(__DIR__ . '/does-not-exist.xml');
    }
}
