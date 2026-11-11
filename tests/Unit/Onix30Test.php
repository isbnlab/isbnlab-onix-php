<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\Model\Product;
use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\OnixVersion;
use PHPUnit\Framework\TestCase;

final class Onix30Test extends TestCase
{
    /** @var array<int, Product> */
    private array $products;

    private OnixReader $reader;

    protected function setUp(): void
    {
        $this->reader = OnixReader::open(__DIR__ . '/../Fixtures/onix30-reference.xml');
        $this->products = iterator_to_array($this->reader);
    }

    public function testReadsEveryProductKeyedByPosition(): void
    {
        self::assertSame([1, 2, 3], array_keys($this->products));
        self::assertSame([], $this->reader->errors());
        self::assertSame(OnixVersion::V30, $this->reader->version());
    }

    public function testHeader(): void
    {
        $header = $this->reader->header();
        self::assertNotNull($header);
        self::assertSame(OnixVersion::V30, $header->version);
        self::assertSame('Example Press Ltd', $header->senderName);
        self::assertSame('Data Team', $header->contactName);
        self::assertSame('data@example.com', $header->emailAddress);
        self::assertSame('5030000000001', $header->senderIdentifiers[0]->value);
        self::assertSame('06', $header->senderIdentifiers[0]->type?->value);
        self::assertSame(['Example Retailer'], $header->addresseeNames);
        self::assertSame('42', $header->messageNumber);
        self::assertSame('20240315T1030+0000', $header->sentDateTime);
        self::assertSame(['Weekly full feed'], $header->messageNotes);
        self::assertSame('eng', $header->defaultLanguageOfText);
        self::assertSame('GBP', $header->defaultCurrencyCode);
    }

    public function testRecordAndIdentifiers(): void
    {
        $product = $this->products[1];
        self::assertSame(OnixVersion::V30, $product->version);
        self::assertSame(1, $product->index);
        self::assertSame('com.example.press.9780306406157', $product->recordReference);
        self::assertSame('03', $product->notificationType?->value);
        self::assertSame(1, $product->notificationType->list);
        self::assertFalse($product->isDeleted());
        self::assertSame('Example Press Ltd', $product->recordSourceName);
        self::assertSame('9780306406157', $product->isbn13());
        self::assertSame('9780306406157', $product->gtin13());
        self::assertNull($product->isbn10());
        self::assertSame('EX-1001', $product->identifier('01'));
        self::assertSame('Example SKU', $product->proprietaryIdentifiers()[0]->typeName);
        self::assertSame([], $product->warnings);
    }

    public function testForm(): void
    {
        $product = $this->products[1];
        self::assertSame('BC', $product->productForm?->value);
        self::assertSame(150, $product->productForm->list);
        self::assertSame('00', $product->productComposition?->value);
        self::assertSame(['B105'], array_map('strval', $product->productFormDetails));
        self::assertSame(['Paperback with flaps'], $product->productFormDescriptions);
        self::assertSame(198.0, $product->measure('01')?->value);
        self::assertSame('mm', $product->measure('01')->unit?->value);
        self::assertSame(310.0, $product->measure('08', 'gr')?->value);
        self::assertNull($product->measure('03'));
    }

    public function testTitles(): void
    {
        $product = $this->products[1];
        self::assertSame("The Lighthouse Keeper's Atlas", $product->title());
        self::assertSame('A Novel', $product->subtitle());
        self::assertSame('The', $product->titleElement()?->prefix);
        self::assertCount(2, $product->titles);
        self::assertSame('11', $product->titles[1]->type?->value);
        self::assertSame('Atlas des gardiens de phare', $product->titles[1]->element()?->title());
    }

    public function testCollection(): void
    {
        $series = $this->products[1]->series();
        self::assertNotNull($series);
        self::assertSame('10', $series->type?->value);
        self::assertSame('Coastal Mysteries', $series->title());
        self::assertSame('3', $series->partNumber());
        self::assertSame('1234-5679', $series->identifiers[0]->value);
        self::assertSame('02', $series->sequences[0]->type?->value);
    }

    public function testCollectionGivenAsCollectionLevelTitleElement(): void
    {
        $product = $this->products[2];
        self::assertSame("The Lighthouse Keeper's Atlas", $product->title());
        self::assertSame('Coastal Mysteries', $product->series()?->title());
        self::assertSame('3', $product->series()->partNumber());
    }

    public function testContributorsAreSortedBySequenceNumber(): void
    {
        $product = $this->products[1];
        self::assertCount(2, $product->contributors);
        $author = $product->contributors[0];
        self::assertSame(1, $author->sequenceNumber);
        self::assertTrue($author->hasRole('A01'));
        self::assertSame('Ada Quill', $author->displayName());
        self::assertSame('Quill, Ada', $author->personNameInverted);
        self::assertSame('0000000000000001', $author->identifiers[0]->value);
        self::assertSame('<p>Ada Quill lives by the <em>sea</em>.</p>', $author->biographicalNotes[0]);
        self::assertSame('Marie Tremblay', $product->contributors[1]->displayName());
        self::assertSame([$author], $product->authors());
        self::assertSame(['By Ada Quill; translated by Marie Tremblay'], $product->contributorStatements);
        self::assertFalse($product->noContributor);
        self::assertTrue($this->products[2]->noContributor);
    }

    public function testEditionLanguageAndExtent(): void
    {
        $product = $this->products[1];
        self::assertSame(['REV'], array_map('strval', $product->editionTypes));
        self::assertSame(2, $product->editionNumber);
        self::assertSame('Second edition, revised', $product->editionStatement);
        self::assertSame('eng', $product->languageCode());
        self::assertSame('fre', $product->languages[1]->code->value);
        // Main content page count (00) wins over the print counterpart (08), whatever the order.
        self::assertSame(336, $product->pageCount());
        self::assertSame('xvi', $product->extents[2]->romanValue);
        self::assertNull($product->numberOfPages);
    }

    public function testSubjects(): void
    {
        $product = $this->products[1];
        self::assertSame(['FIC031000', 'FIC022000'], $product->bisacCodes());
        self::assertSame(['FF'], $product->themaCodes());
        self::assertSame(['lighthouse', 'maps', 'cartography', 'coast'], $product->keywords());
        $main = $product->subjectsInScheme('10')[1];
        self::assertTrue($main->main);
        self::assertSame('FICTION / Thrillers / General', $main->headingText);
        self::assertSame('2023', $product->subjectsInScheme('10')[0]->schemeVersion);
    }

    public function testTexts(): void
    {
        $product = $this->products[1];
        $description = $product->description();
        self::assertNotNull($description);
        self::assertSame('03', $description->type?->value);
        self::assertSame('05', $description->format);
        self::assertSame('eng', $description->language);
        self::assertSame('<p>When the light goes out, <strong>Ada</strong> finds a map.</p><p>Then the storm comes.</p>', $description->text);
        self::assertSame('When the light goes out, Ada finds a map. Then the storm comes.', $description->plainText());
        self::assertSame('00', $description->audiences[0]->value);
        self::assertSame('A keeper, a map, a storm.', $product->textsOfType('02')[0]->text);
        $toc = $product->textsOfType('04')[0];
        self::assertSame('<ol><li>The Light</li><li>The Map</li></ol>', $toc->text);
        self::assertSame('The Light The Map', $toc->plainText());
    }

    public function testResources(): void
    {
        $product = $this->products[1];
        self::assertSame('https://images.example.com/9780306406157.jpg', $product->frontCoverUrl());
        self::assertCount(2, $product->resources);
        self::assertSame('03', $product->resources[0]->mode?->value);
        self::assertSame('02', $product->resources[0]->versions[0]->form?->value);
        self::assertNull($this->products[2]->frontCoverUrl());
    }

    public function testPublishing(): void
    {
        $product = $this->products[1];
        self::assertSame('Example Press Ltd', $product->publisherName());
        self::assertSame('https://press.example.com/', $product->publishers[0]->websites[0]);
        self::assertSame('Harbour Books', $product->imprintName());
        self::assertSame('HARBOUR', $product->imprints[0]->identifiers[0]->value);
        self::assertSame(['London'], $product->citiesOfPublication);
        self::assertSame('GB', $product->countryOfPublication);
        self::assertSame('04', $product->publishingStatus?->value);
        self::assertSame('20240315', $product->publicationDate()?->value);
        self::assertSame('2024-03-15', $product->publicationDate()->toDateTime()?->format('Y-m-d'));
        self::assertSame('2019-01-01', $product->publishingDate('11')?->toDateTime()?->format('Y-m-d'));
        self::assertSame('05', $product->publishingDate('11')->format?->value);
        $rights = $product->salesRights[0];
        self::assertSame('01', $rights->type?->value);
        self::assertTrue($rights->territory->includesCountry('GB'));
        self::assertFalse($rights->territory->includesCountry('US'));
    }

    public function testRelatedMaterial(): void
    {
        $product = $this->products[1];
        self::assertSame('W-555', $product->workIdentifiers()[0]->value);
        self::assertSame('Example work ID', $product->workIdentifiers()[0]->typeName);
        $related = $product->relatedProducts[0];
        self::assertTrue($related->hasRelation('06'));
        self::assertSame('9780306406164', $related->isbn13());
        self::assertSame('EA', $related->productForm?->value);
    }

    public function testSupply(): void
    {
        $product = $this->products[1];
        self::assertCount(2, $product->supplyDetails);
        $uk = $product->supplyDetails[0];
        self::assertSame('Example Distribution', $uk->supplierName);
        self::assertSame('01', $uk->supplierRole?->value);
        self::assertSame('5030000000002', $uk->supplierIdentifiers[0]->value);
        self::assertSame('21', $uk->availability?->value);
        self::assertSame(['GB', 'IE'], $uk->market->countriesIncluded);
        self::assertSame('20240301', $uk->dates[0]->value);
        self::assertSame(9.99, $uk->prices[0]->amount);
        self::assertSame(0.0, $uk->prices[0]->taxRatePercent);
        // A decimal comma is read as a decimal point.
        self::assertSame(12.5, $product->price('EUR')?->amount);
        self::assertSame(['IE'], $product->price('EUR')->territory->countriesIncluded);
        self::assertSame(17.0, $product->price('USD', '01')?->amount);
        self::assertNull($product->price('USD', '02'));
        self::assertCount(3, $product->prices());
        self::assertSame(['US'], $product->supplyDetails[1]->market->countriesIncluded);
    }

    public function testMinimalAndDeletedProducts(): void
    {
        $product = $this->products[2];
        self::assertSame('EA', $product->productForm?->value);
        self::assertSame([], $product->contributors);
        self::assertNull($product->pageCount());
        self::assertSame(['ExtentValue "about three hundred" is not a number'], $product->warnings);

        $deleted = $this->products[3];
        self::assertTrue($deleted->isDeleted());
        self::assertSame('Cancelled before publication', $deleted->deletionText);
        self::assertNull($deleted->title());
        self::assertNull($deleted->description());
        self::assertSame([], $deleted->supplyDetails);
    }

    public function testCanBeIteratedTwice(): void
    {
        self::assertCount(3, iterator_to_array($this->reader->products()));
        self::assertCount(3, iterator_to_array($this->reader->products()));
    }
}
