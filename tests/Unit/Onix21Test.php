<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\Model\Product;
use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\OnixVersion;
use PHPUnit\Framework\TestCase;

final class Onix21Test extends TestCase
{
    /** @var array<int, Product> */
    private array $products;

    private OnixReader $reader;

    protected function setUp(): void
    {
        $this->reader = OnixReader::open(__DIR__ . '/../Fixtures/onix21-reference.xml');
        $this->products = iterator_to_array($this->reader);
    }

    public function testVersionIsDetectedFromStructure(): void
    {
        self::assertNull($this->reader->version());
        self::assertCount(2, $this->products);
        self::assertSame(OnixVersion::V21, $this->products[1]->version);
        self::assertSame(OnixVersion::V21, $this->products[2]->version);
        self::assertSame([], $this->reader->errors());
    }

    public function testHeader(): void
    {
        $header = $this->reader->header();
        self::assertNotNull($header);
        self::assertSame('Example Books Pty Ltd', $header->senderName);
        self::assertSame('Feed Desk', $header->contactName);
        self::assertSame('feeds@example.com', $header->emailAddress);
        self::assertSame('1234567', $header->senderIdentifiers[0]->value);
        self::assertSame(['Example Retailer'], $header->addresseeNames);
        self::assertSame('20240102', $header->sentDateTime);
        self::assertSame('AUD', $header->defaultCurrencyCode);
        self::assertSame('02', $header->defaultPriceType?->value);
    }

    public function testFullRecord(): void
    {
        $product = $this->products[1];
        self::assertSame('EXB-9781234567897', $product->recordReference);
        self::assertSame('9781234567897', $product->isbn13());
        self::assertSame('123456789X', $product->isbn10());
        self::assertSame('BB', $product->productForm?->value);
        self::assertSame(7, $product->productForm->list);
        self::assertSame(78, $product->productFormDetails[0]->list);

        // HTML entities from the DTD are resolved.
        self::assertSame('Café Gardens & Courtyards', $product->title());
        self::assertSame('Planting for Small Spaces — Revised', $product->subtitle());

        self::assertSame('Gardens of the South', $product->series()?->title());
        self::assertSame('2', $product->series()->partNumber());
        self::assertSame('GARDENS', $product->series()->identifiers[0]->value);

        self::assertSame('Tomás Brennan', $product->contributors[0]->displayName());
        self::assertSame(['Tomás Brennan designs small gardens.'], $product->contributors[0]->biographicalNotes);
        self::assertSame('Example Photo Agency', $product->contributors[1]->displayName());
        self::assertCount(1, $product->authors());

        self::assertSame(['REV'], array_map('strval', $product->editionTypes));
        self::assertSame(3, $product->editionNumber);
        self::assertSame('eng', $product->languageCode());
        self::assertSame(224, $product->pageCount());

        self::assertSame(['GAR013000', 'GAR019000'], $product->bisacCodes());
        self::assertSame('2.8', $product->subjectsInScheme('10')[0]->schemeVersion);
        self::assertSame(['WMD'], $product->themaCodes());
        self::assertSame(26, $product->subjectsInScheme('93')[0]->scheme?->list);
        self::assertSame(['gardening', 'courtyards', 'small spaces'], $product->keywords());

        self::assertSame('<p>Turn a <b>courtyard</b> into a garden.</p>', $product->description()?->text);
        self::assertSame('02', $product->description()->format);
        self::assertSame('Turn a courtyard into a garden.', $product->description()->plainText());
        self::assertSame('https://images.example.com/9781234567897.jpg', $product->frontCoverUrl());

        self::assertSame('Example Books Pty Ltd', $product->publisherName());
        self::assertSame('EXB', $product->publishers[0]->identifiers[0]->value);
        self::assertSame('Example Garden Books', $product->imprintName());
        self::assertSame('20240201', $product->publicationDate()?->value);
        self::assertSame('2015', $product->publishingDate('11')?->value);
        self::assertSame(['AU', 'NZ'], $product->salesRights[0]->territory->countriesIncluded);
        self::assertSame(235.0, $product->measure('01', 'mm')?->value);
        self::assertSame(9.25, $product->measure('01')?->value);

        self::assertSame('WK-778', $product->workIdentifiers()[0]->value);
        self::assertSame('9781234567880', $product->relatedProducts[0]->isbn13());

        $supply = $product->supplyDetails[0];
        self::assertSame('Example Distribution AU', $supply->supplierName);
        // ProductAvailability (list 65) is preferred over the 2.1-only AvailabilityCode.
        self::assertSame(65, $supply->availability?->list);
        self::assertSame('21', $supply->availability->value);
        self::assertSame('20240201', $supply->dates[0]->value);
        self::assertSame(39.99, $product->price('AUD')?->amount);
        self::assertSame(['NZ'], $product->price('NZD')?->territory->countriesIncluded);
    }

    public function testLegacyElements(): void
    {
        $product = $this->products[2];
        self::assertSame('0987654321', $product->isbn10());
        self::assertSame('9780987654325', $product->gtin13());
        self::assertSame('02', $product->notificationType?->value, 'a dropped leading zero is restored');
        self::assertSame('Old School Record', $product->title());
        self::assertSame('Lee Example', $product->authors()[0]->displayName());
        self::assertSame('Bare Publisher Name', $product->publisherName());
        self::assertSame('2001', $product->publicationDate()?->value);
        self::assertSame(54, $product->supplyDetails[0]->availability?->list);
        self::assertSame('OP', $product->supplyDetails[0]->availability->value);
        self::assertNull($product->supplyDetails[0]->prices[0]->amount);
        self::assertSame(['PriceAmount "n/a" is not a number'], $product->warnings);
    }
}
