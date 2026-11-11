<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\Model\Code;
use ISBNLab\Onix\Model\Contributor;
use ISBNLab\Onix\Model\ProductDate;
use ISBNLab\Onix\Model\Territory;
use ISBNLab\Onix\Model\TextContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public static function dates(): iterable
    {
        yield 'full date' => ['20240315', null, '2024-03-15'];
        yield 'year and month' => ['202403', '01', '2024-03-01'];
        yield 'year' => ['2024', '05', '2024-01-01'];
        yield 'dashed' => ['2024-03-15', null, '2024-03-15'];
        yield 'with time' => ['20240315T1030', '13', '2024-03-15'];
        yield 'impossible' => ['20240230', null, null];
        yield 'quarter is not converted' => ['20241', '03', null];
        yield 'text' => ['Spring 2024', '12', null];
    }

    #[DataProvider('dates')]
    public function testProductDate(string $value, ?string $format, ?string $expected): void
    {
        $date = new ProductDate(null, $value, null === $format ? null : new Code(55, $format));
        self::assertSame($expected, $date->toDateTime()?->format('Y-m-d'));
    }

    public function testContributorDisplayName(): void
    {
        self::assertSame('Dr Jane van Dyke Jr, PhD', (new Contributor(
            roles: [],
            titlesBeforeNames: 'Dr',
            namesBeforeKey: 'Jane',
            prefixToKey: 'van',
            keyNames: 'Dyke',
            namesAfterKey: 'Jr',
            suffixToKey: 'PhD',
        ))->displayName());
        self::assertSame('Jane Austen', (new Contributor(roles: [], personNameInverted: 'Austen, Jane'))->displayName());
        self::assertSame('Plato', (new Contributor(roles: [], personNameInverted: 'Plato'))->displayName());
        self::assertSame('Example Org', (new Contributor(roles: [], corporateName: 'Example Org'))->displayName());
        self::assertNull((new Contributor(roles: []))->displayName());
    }

    public function testTerritory(): void
    {
        $territory = new Territory(['GB'], [], [], []);
        self::assertTrue($territory->includesCountry('GB'));
        self::assertFalse($territory->includesCountry('FR'));
        self::assertTrue((new Territory())->isEmpty());
    }

    public function testCode(): void
    {
        $code = new Code(5, '15', 'ISBN-13');
        self::assertTrue($code->is('03', '15'));
        self::assertFalse($code->is('02'));
        self::assertSame('15', (string) $code);
    }

    public function testPlainText(): void
    {
        $text = new TextContent(null, '<p>One&nbsp;<em>two</em></p><p>three &amp; four</p>');
        self::assertSame('One two three & four', $text->plainText(), 'non-breaking spaces collapse too');
    }
}
