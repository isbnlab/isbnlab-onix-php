<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\CodeList\EditeurDownload;
use ISBNLab\Onix\Internal\TagMap;
use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\OnixVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Onix313Test extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function messages(): array
    {
        $reference = <<<'XML'
            <ONIXMessage release="3.1" xmlns="http://ns.editeur.org/onix/3.1/reference"><Product>
            <RecordReference>x</RecordReference>
            <CollateralDetail><TextContent><TextType>03</TextType><ContentAudience>00</ContentAudience>
            <Text language="hin" textscript="Deva">विवरण</Text><Text language="hin" textscript="Latn">vivaran</Text>
            <TextSource><PersonName>A Reviewer</PersonName></TextSource>
            </TextContent></CollateralDetail>
            <PublishingDetail>
            <Imprint><ImprintNameInverted>Atelier, Éditions de l'</ImprintNameInverted></Imprint>
            <Publisher><PublishingRole>01</PublishingRole><PublisherNameInverted>Minuit, Les Éditions de</PublisherNameInverted></Publisher>
            <Publisher><PublishingRole>02</PublishingRole><PublisherName>Éditions Apogée</PublisherName><PublisherNameInverted>Apogée, Éditions</PublisherNameInverted></Publisher>
            </PublishingDetail></Product></ONIXMessage>
            XML;
        $short = strtr($reference, [
            'ONIXMessage' => 'ONIXmessage', '3.1/reference' => '3.1/short', 'Product>' => 'product>',
            'RecordReference' => 'a001', 'CollateralDetail' => 'collateraldetail', 'TextContent' => 'textcontent',
            'TextType' => 'x426', 'ContentAudience' => 'x427', '<Text ' => '<d104 ', '</Text>' => '</d104>',
            'TextSource' => 'textsource', 'PersonName' => 'b036', 'PublishingDetail' => 'publishingdetail',
            'ImprintNameInverted' => 'x593', '<Imprint>' => '<imprint>', '</Imprint>' => '</imprint>',
            'PublisherNameInverted' => 'x592', 'PublisherName' => 'b081', 'PublishingRole' => 'b291',
            '<Publisher>' => '<publisher>', '</Publisher>' => '</publisher>',
        ]);

        return ['reference names' => [$reference], 'short tags' => [$short]];
    }

    #[DataProvider('messages')]
    public function testInvertedNamesAndTextScripts(string $xml): void
    {
        $product = iterator_to_array(OnixReader::fromString($xml))[1];

        self::assertSame(OnixVersion::V31, $product->version);
        self::assertSame('Minuit, Les Éditions de', $product->publisherName());
        self::assertSame('Minuit, Les Éditions de', $product->publishers[0]->nameInverted);
        self::assertSame('Éditions Apogée', $product->publishers[1]->name);
        self::assertSame('Apogée, Éditions', $product->publishers[1]->nameInverted);
        self::assertSame("Atelier, Éditions de l'", $product->imprintName());
        self::assertSame("Atelier, Éditions de l'", $product->imprints[0]->nameInverted);
        self::assertSame(['Deva', 'Latn'], array_map(static fn($text) => $text->script, $product->texts));
        self::assertSame(['hin', 'hin'], array_map(static fn($text) => $text->language, $product->texts));
    }

    public function testEveryElementAddedSince30HasItsShortTag(): void
    {
        $added = [
            'affiliationidentifier' => 'AffiliationIdentifier', 'epublicensedate' => 'EpubLicenseDate',
            'prizeidentifier' => 'PrizeIdentifier', 'textsource' => 'TextSource',
            'x580' => 'AffiliationIDType', 'x581' => 'TextSourceLink', 'x582' => 'CollectionFrequency',
            'x583' => 'MainAudience', 'x584' => 'AwardingBody', 'x585' => 'EpubLicenseDateRole',
            'x586' => 'CollectionElementLevel', 'x587' => 'MarketReference', 'x588' => 'CopyrightStatementText',
            'x589' => 'PrizeIDType', 'x590' => 'PostalCode', 'x591' => 'SubjectDescription',
            'x592' => 'PublisherNameInverted', 'x593' => 'ImprintNameInverted',
        ];
        foreach ($added as $short => $reference) {
            self::assertSame($reference, TagMap::toReference($short));
        }
    }

    public function testLastModifiedHeaderIsReadAsGmt(): void
    {
        $method = new \ReflectionMethod(EditeurDownload::class, 'parseHttpDate');
        $date = $method->invoke(null, 'Mon, 09 Nov 2026 12:30:00 GMT');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-11-09T12:30:00+00:00', $date->format(\DATE_ATOM));
    }
}
