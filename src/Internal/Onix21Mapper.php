<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

use ISBNLab\Onix\Model\Code;
use ISBNLab\Onix\Model\Collection;
use ISBNLab\Onix\Model\Contributor;
use ISBNLab\Onix\Model\Extent;
use ISBNLab\Onix\Model\Identifier;
use ISBNLab\Onix\Model\Imprint;
use ISBNLab\Onix\Model\Language;
use ISBNLab\Onix\Model\Price;
use ISBNLab\Onix\Model\Product;
use ISBNLab\Onix\Model\ProductDate;
use ISBNLab\Onix\Model\Publisher;
use ISBNLab\Onix\Model\RelatedProduct;
use ISBNLab\Onix\Model\RelatedWork;
use ISBNLab\Onix\Model\ResourceVersion;
use ISBNLab\Onix\Model\SalesRights;
use ISBNLab\Onix\Model\Subject;
use ISBNLab\Onix\Model\SupplyDetail;
use ISBNLab\Onix\Model\SupportingResource;
use ISBNLab\Onix\Model\Territory;
use ISBNLab\Onix\Model\TextContent;
use ISBNLab\Onix\Model\TitleDetail;
use ISBNLab\Onix\Model\TitleElement;
use ISBNLab\Onix\OnixVersion;

/**
 * Maps an ONIX 2.1 Product element onto the ONIX 3.0-shaped model. ONIX 2.1 is
 * obsolete (EDItEUR retired it in 2023) but still sent by many suppliers.
 *
 * Code lists that only existed in 2.1 (7 product form, 33 text type, 38 and 40 media
 * files, 54 availability, 78 form detail) keep their 2.1 list numbers. Product form
 * and form detail labels are taken from their 3.0 successors (150, 175), which kept
 * the 2.1 codes.
 *
 * @internal
 */
final class Onix21Mapper extends Mapper
{
    /** Legacy identifier elements and the list 5 type each stands for. */
    private const LEGACY_IDENTIFIERS = [
        'PublisherProductNo' => '01',
        'ISBN' => '02',
        'EAN13' => '03',
        'UPC' => '04',
        'ISMN' => '05',
        'DOI' => '06',
    ];

    public function map(Element $product, ?int $index): Product
    {
        return new Product(
            version: OnixVersion::V21,
            recordReference: $product->text('RecordReference'),
            notificationType: $this->code(1, $product->text('NotificationType')),
            identifiers: $this->productIdentifiers($product),
            deletionText: $product->text('DeletionText'),
            recordSourceName: $product->text('RecordSourceName'),
            productForm: $this->code(7, $product->text('ProductForm'), 150),
            productFormDetails: $this->codes(78, $product, 'ProductFormDetail', 175),
            productFormDescriptions: $product->texts('ProductFormDescription'),
            measures: $this->measures($product, 'MeasureTypeCode'),
            collections: $this->collections($product),
            titles: $this->titleDetails($product),
            contributors: $this->contributors($product),
            contributorStatements: $product->texts('ContributorStatement'),
            noContributor: $product->has('NoContributor'),
            editionTypes: $this->codes(21, $product, 'EditionTypeCode'),
            editionNumber: $this->integer($product->text('EditionNumber'), 'EditionNumber'),
            editionStatement: $product->text('EditionStatement'),
            languages: $this->languages($product),
            extents: $this->extents($product),
            numberOfPages: $this->integer($product->text('NumberOfPages'), 'NumberOfPages'),
            subjects: $this->subjects($product),
            texts: $this->texts($product),
            resources: $this->mediaFiles($product),
            imprints: $this->imprints($product),
            publishers: $this->publishers($product),
            citiesOfPublication: $product->texts('CityOfPublication'),
            countryOfPublication: $product->text('CountryOfPublication'),
            publishingStatus: $this->code(64, $product->text('PublishingStatus')),
            publishingDates: $this->publishingDates($product),
            salesRights: $this->salesRights($product),
            relatedWorks: $this->relatedWorks($product),
            relatedProducts: $this->relatedProducts($product),
            supplyDetails: $this->supplyDetails($product),
            index: $index,
            warnings: $this->warnings,
        );
    }

    /**
     * @return list<Identifier>
     */
    private function productIdentifiers(Element $parent): array
    {
        $identifiers = $this->identifiers($parent, 'ProductIdentifier', 'ProductIDType', 5);
        foreach (self::LEGACY_IDENTIFIERS as $element => $type) {
            $value = $parent->text($element);
            if (null === $value) {
                continue;
            }
            foreach ($identifiers as $identifier) {
                if ($identifier->isType($type) && $identifier->value === $value) {
                    continue 2;
                }
            }
            $identifiers[] = new Identifier($this->code(5, $type), $value);
        }

        return $identifiers;
    }

    /**
     * @return list<TitleDetail>
     */
    private function titleDetails(Element $product): array
    {
        $details = [];
        $productLevel = $this->code(149, '01');
        foreach ($product->children('Title') as $title) {
            $details[] = new TitleDetail($this->code(15, $title->text('TitleType')), [$this->titleElement($title, $productLevel)]);
        }

        $hasDistinctive = false;
        foreach ($details as $detail) {
            $hasDistinctive = $hasDistinctive || (null !== $detail->type && $detail->type->is('01'));
        }
        $distinctive = $product->text('DistinctiveTitle');
        $withoutPrefix = $product->text('TitleWithoutPrefix');
        if (!$hasDistinctive && (null !== $distinctive || null !== $withoutPrefix)) {
            array_unshift($details, new TitleDetail($this->code(15, '01'), [new TitleElement(
                level: $productLevel,
                text: $distinctive,
                prefix: $product->text('TitlePrefix'),
                withoutPrefix: $withoutPrefix,
                subtitle: $product->text('Subtitle'),
            )]));
        }

        return $details;
    }

    /**
     * @return list<Contributor>
     */
    private function contributors(Element $parent): array
    {
        $contributors = [];
        foreach ($parent->children('Contributor') as $element) {
            $contributors[] = $this->contributor($element, $this->identifiers($element, 'PersonNameIdentifier', 'PersonNameIDType', 101));
        }

        return $this->sortBySequence($contributors);
    }

    /**
     * Series and Set composites, as collections.
     *
     * @return list<Collection>
     */
    private function collections(Element $product): array
    {
        $collectionLevel = $this->code(149, '02');
        $collections = [];
        foreach ($product->children('Series') as $series) {
            $titles = [];
            $name = $series->text('TitleOfSeries');
            $number = $series->text('NumberWithinSeries');
            if (null !== $name) {
                $titles[] = new TitleDetail($this->code(15, '01'), [new TitleElement(level: $collectionLevel, text: $name, partNumber: $number)]);
            }
            foreach ($series->children('Title') as $title) {
                $element = $this->titleElement($title, $collectionLevel);
                if (null !== $number && null === $element->partNumber) {
                    $element = new TitleElement($collectionLevel, $element->text, $element->prefix, $element->withoutPrefix, $element->subtitle, $number);
                }
                $titles[] = new TitleDetail($this->code(15, $title->text('TitleType')), [$element]);
            }
            $collections[] = new Collection(
                type: null,
                identifiers: $this->identifiers($series, 'SeriesIdentifier', 'SeriesIDType', 13),
                titles: $titles,
                contributors: $this->contributors($series),
            );
        }
        foreach ($product->children('Set') as $set) {
            $name = $set->text('TitleOfSet');
            $collections[] = new Collection(
                type: null,
                identifiers: $this->identifiers($set, 'ProductIdentifier', 'ProductIDType', 5),
                titles: null === $name ? [] : [new TitleDetail($this->code(15, '01'), [
                    new TitleElement(level: $collectionLevel, text: $name, partNumber: $set->text('ItemNumberWithinSet') ?? $set->text('SetPartNumber')),
                ])],
            );
        }

        return $collections;
    }

    /**
     * @return list<Language>
     */
    private function languages(Element $product): array
    {
        $languages = [];
        foreach ($product->children('Language') as $element) {
            $code = $this->code(74, $element->text('LanguageCode'));
            if (null !== $code) {
                $languages[] = new Language($this->code(22, $element->text('LanguageRole')), $code);
            }
        }
        foreach ($product->texts('LanguageOfText') as $value) {
            $code = $this->code(74, $value);
            if (null !== $code) {
                $languages[] = new Language($this->code(22, '01'), $code);
            }
        }

        return $languages;
    }

    /**
     * @return list<Extent>
     */
    private function extents(Element $product): array
    {
        $extents = [];
        foreach ($product->children('Extent') as $element) {
            $raw = $element->text('ExtentValue');
            $extents[] = new Extent(
                type: $this->code(23, $element->text('ExtentType')),
                value: $this->number($raw, 'ExtentValue'),
                unit: $this->code(24, $element->text('ExtentUnit')),
                rawValue: $raw,
            );
        }

        return $extents;
    }

    /**
     * MainSubject, BASICMainSubject (BISAC), BICMainSubject and Subject, as subjects.
     *
     * @return list<Subject>
     */
    private function subjects(Element $product): array
    {
        $subjects = [];
        foreach (['BASICMainSubject', 'BISACMainSubject'] as $name) {
            foreach ($product->texts($name) as $code) {
                $subjects[] = new Subject($this->code(27, '10'), $code, null, true, null, $product->text('BASICVersion'));
            }
        }
        foreach ($product->texts('BICMainSubject') as $code) {
            $subjects[] = new Subject($this->code(27, '12'), $code, null, true, null, $product->text('BICVersion'));
        }
        foreach ($product->children('MainSubject') as $element) {
            $subjects[] = $this->subject($element, true, 26);
        }
        foreach ($product->children('Subject') as $element) {
            $subjects[] = $this->subject($element, false);
        }

        return $subjects;
    }

    /**
     * @return list<TextContent>
     */
    private function texts(Element $product): array
    {
        $texts = [];
        foreach ($product->children('OtherText') as $element) {
            $textElement = $element->first('Text');
            if (null === $textElement) {
                continue;
            }
            $text = $this->text($textElement, $this->code(33, $element->text('TextTypeCode')), $textElement->attribute('textformat') ?? $element->text('TextFormat'));
            if (null !== $text) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return list<SupportingResource>
     */
    private function mediaFiles(Element $product): array
    {
        $resources = [];
        foreach ($product->children('MediaFile') as $element) {
            $link = $element->text('MediaFileLink');
            $resources[] = new SupportingResource(
                $this->code(38, $element->text('MediaFileTypeCode')),
                null === $link ? [] : [new ResourceVersion(
                    $this->code(40, $element->text('MediaFileLinkTypeCode')),
                    $link,
                    $this->code(39, $element->text('MediaFileFormatCode')),
                )],
            );
        }

        return $resources;
    }

    /**
     * @return list<Identifier>
     */
    private function nameCodes(Element $element): array
    {
        $value = $element->text('NameCodeValue');
        if (null === $value) {
            return [];
        }

        return [new Identifier($this->code(44, $element->text('NameCodeType')), $value, $element->text('NameCodeTypeName'))];
    }

    /**
     * @return list<Imprint>
     */
    private function imprints(Element $product): array
    {
        $imprints = [];
        foreach ($product->children('Imprint') as $element) {
            $imprints[] = new Imprint($element->text('ImprintName'), $this->nameCodes($element));
        }

        return $imprints;
    }

    /**
     * @return list<Publisher>
     */
    private function publishers(Element $product): array
    {
        $publishers = [];
        foreach ($product->children('Publisher') as $element) {
            $websites = [];
            foreach ($element->children('Website') as $website) {
                $link = $website->text('WebsiteLink');
                if (null !== $link) {
                    $websites[] = $link;
                }
            }
            $publishers[] = new Publisher(
                name: $element->text('PublisherName'),
                role: $this->code(45, $element->text('PublishingRole')),
                identifiers: $this->nameCodes($element),
                websites: $websites,
            );
        }
        // Very old records carry a bare PublisherName on the product.
        $bare = $product->text('PublisherName');
        if ([] === $publishers && null !== $bare) {
            $publishers[] = new Publisher($bare, $this->code(45, '01'));
        }

        return $publishers;
    }

    /**
     * @return list<ProductDate>
     */
    private function publishingDates(Element $product): array
    {
        $dates = [];
        foreach ([['PublicationDate', '01'], ['YearFirstPublished', '11'], ['AnnouncementDate', '09'], ['TradeAnnouncementDate', '10'], ['OutOfPrintDate', '13']] as [$name, $role]) {
            $value = $product->text($name);
            if (null !== $value) {
                $dates[] = new ProductDate($this->code(163, $role), $value);
            }
        }

        return $dates;
    }

    /**
     * @return list<SalesRights>
     */
    private function salesRights(Element $product): array
    {
        $rights = [];
        foreach ($product->children('SalesRights') as $element) {
            $countries = [];
            foreach ($element->texts('RightsCountry') as $value) {
                array_push($countries, ...$this->splitList($value));
            }
            $regions = [];
            foreach ([...$element->texts('RightsTerritory'), ...$element->texts('RightsRegion')] as $value) {
                array_push($regions, ...$this->splitList($value));
            }
            $rights[] = new SalesRights($this->code(46, $element->text('SalesRightsType')), new Territory($countries, $regions));
        }

        return $rights;
    }

    /**
     * Product-level WorkIdentifiers, as a "manifestation of" related work.
     *
     * @return list<RelatedWork>
     */
    private function relatedWorks(Element $product): array
    {
        $identifiers = $this->identifiers($product, 'WorkIdentifier', 'WorkIDType', 16);

        return [] === $identifiers ? [] : [new RelatedWork($this->code(164, '01'), $identifiers)];
    }

    /**
     * @return list<RelatedProduct>
     */
    private function relatedProducts(Element $product): array
    {
        $products = [];
        foreach ($product->children('RelatedProduct') as $element) {
            $products[] = new RelatedProduct(
                $this->codes(51, $element, 'RelationCode'),
                $this->productIdentifiers($element),
                $this->code(7, $element->text('ProductForm'), 150),
            );
        }

        return $products;
    }

    /**
     * @return list<SupplyDetail>
     */
    private function supplyDetails(Element $product): array
    {
        $details = [];
        foreach ($product->children('SupplyDetail') as $element) {
            $supplierIdentifiers = $this->identifiers($element, 'SupplierIdentifier', 'SupplierIDType', 92);
            foreach ([['SupplierSAN', '07'], ['SupplierEANLocationNumber', '06']] as [$name, $type]) {
                $value = $element->text($name);
                if (null !== $value) {
                    $supplierIdentifiers[] = new Identifier($this->code(92, $type), $value);
                }
            }
            $dates = [];
            foreach ([['OnSaleDate', '02'], ['ExpectedShipDate', '08'], ['LastDateForReturns', '18']] as [$name, $role]) {
                $value = $element->text($name);
                if (null !== $value) {
                    $dates[] = new ProductDate($this->code(166, $role), $value);
                }
            }
            $details[] = new SupplyDetail(
                supplierName: $element->text('SupplierName'),
                supplierRole: $this->code(93, $element->text('SupplierRole')),
                supplierIdentifiers: $supplierIdentifiers,
                availability: $this->availability($element),
                dates: $dates,
                prices: $this->prices($element),
                unpricedItemType: $this->code(57, $element->text('UnpricedItemType')),
            );
        }

        return $details;
    }

    private function availability(Element $supplyDetail): ?Code
    {
        return $this->code(65, $supplyDetail->text('ProductAvailability'))
            ?? $this->code(54, $supplyDetail->text('AvailabilityCode'));
    }

    /**
     * @return list<Price>
     */
    private function prices(Element $supplyDetail): array
    {
        $prices = [];
        foreach ($supplyDetail->children('Price') as $element) {
            $countries = [];
            foreach ($element->texts('CountryCode') as $value) {
                array_push($countries, ...$this->splitList($value));
            }
            $regions = [];
            foreach ($element->texts('Territory') as $value) {
                array_push($regions, ...$this->splitList($value));
            }
            $prices[] = new Price(
                type: $this->code(58, $element->text('PriceTypeCode')),
                amount: $this->number($element->text('PriceAmount'), 'PriceAmount'),
                currencyCode: $element->text('CurrencyCode'),
                territory: new Territory($countries, $regions, $this->splitList($element->text('CountryExcluded')), $this->splitList($element->text('TerritoryExcluded'))),
                qualifier: $this->code(59, $element->text('PriceQualifier')),
                taxRatePercent: $this->number($element->text('TaxRatePercent1'), 'TaxRatePercent1'),
            );
        }

        return $prices;
    }
}
