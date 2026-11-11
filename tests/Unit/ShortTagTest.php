<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\OnixVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShortTagTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, OnixVersion|null}>
     */
    public static function pairs(): iterable
    {
        yield 'ONIX 3.0' => ['onix30-reference.xml', 'onix30-short.xml', OnixVersion::V30];
        yield 'ONIX 2.1' => ['onix21-reference.xml', 'onix21-short.xml', null];
    }

    #[DataProvider('pairs')]
    public function testShortTagsReadTheSameAsReferenceNames(string $reference, string $short, ?OnixVersion $version): void
    {
        $referenceReader = OnixReader::open(__DIR__ . '/../Fixtures/' . $reference);
        $shortReader = OnixReader::open(__DIR__ . '/../Fixtures/' . $short);

        $referenceProducts = iterator_to_array($referenceReader);
        $shortProducts = iterator_to_array($shortReader);

        self::assertNotEmpty($referenceProducts);
        self::assertEquals($referenceProducts, $shortProducts);
        self::assertEquals($referenceReader->header(), $shortReader->header());
        self::assertSame($version, $shortReader->version());
    }
}
