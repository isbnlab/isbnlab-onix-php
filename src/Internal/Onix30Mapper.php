<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

use ISBNLab\Onix\Model\Collection;
use ISBNLab\Onix\Model\CollectionSequence;
use ISBNLab\Onix\Model\Contributor;
use ISBNLab\Onix\Model\Extent;
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
use ISBNLab\Onix\OnixVersion;

/**
 * Maps an ONIX 3.0 or 3.1 Product element.
 *
 * @internal
 */
final class Onix30Mapper extends Mapper
{
    public function map(Element $product, OnixVersion $version, ?int $index): Product
    {
        $descriptive = $product->first('DescriptiveDetail');
        $collateral = $product->first('CollateralDetail');
        $publishing = $product->first('PublishingDetail');
        $related = $product->first('RelatedMaterial');

        return new Product(
            version: $version,
            recordReference: $product->text('RecordReference'),
            notificationType: $this->code(1, $product->text('NotificationType')),
            identifiers: $this->identifiers($product, 'ProductIdentifier', 'ProductIDType', 5),
            deletionText: $product->text('DeletionText'),
            recordSourceName: $product->text('RecordSourceName'),
            productComposition: $this->code(2, $descriptive?->text('ProductComposition')),
            productForm: $this->code(150, $descriptive?->text('ProductForm')),
            productFormDetails: null === $descriptive ? [] : $this->codes(175, $descriptive, 'ProductFormDetail'),
            productFormDescriptions: $descriptive?->texts('ProductFormDescription') ?? [],
            measures: null === $descriptive ? [] : $this->measures($descriptive, 'MeasureType'),
            collections: null === $descriptive ? [] : $this->collections($descriptive),
            noCollection: $descriptive?->has('NoCollection') ?? false,
            titles: null === $descriptive ? [] : $this->titleDetails($descriptive),
            contributors: null === $descriptive ? [] : $this->contributors($descriptive),
            contributorStatements: $descriptive?->texts('ContributorStatement') ?? [],
            noContributor: $descriptive?->has('NoContributor') ?? false,
            editionTypes: null === $descriptive ? [] : $this->codes(21, $descriptive, 'EditionType'),
            editionNumber: $this->integer($descriptive?->text('EditionNumber'), 'EditionNumber'),
            editionStatement: $descriptive?->text('EditionStatement'),
            languages: null === $descriptive ? [] : $this->languages($descriptive),
            extents: null === $descriptive ? [] : $this->extents($descriptive),
            subjects: null === $descriptive ? [] : $this->subjects($descriptive),
            texts: null === $collateral ? [] : $this->texts($collateral),
            resources: null === $collateral ? [] : $this->resources($collateral),
            imprints: null === $publishing ? [] : $this->imprints($publishing),
            publishers: null === $publishing ? [] : $this->publishers($publishing),
            citiesOfPublication: $publishing?->texts('CityOfPublication') ?? [],
            countryOfPublication: $publishing?->text('CountryOfPublication'),
            publishingStatus: $this->code(64, $publishing?->text('PublishingStatus')),
            publishingDates: null === $publishing ? [] : $this->dates($publishing, 'PublishingDate', 'PublishingDateRole', 163),
            salesRights: null === $publishing ? [] : $this->salesRights($publishing),
            relatedWorks: null === $related ? [] : $this->relatedWorks($related),
            relatedProducts: null === $related ? [] : $this->relatedProducts($related),
            supplyDetails: $this->supplyDetails($product),
            index: $index,
            warnings: $this->warnings,
        );
    }

    /**
     * @return list<TitleDetail>
     */
    private function titleDetails(Element $parent): array
    {
        $details = [];
        foreach ($parent->children('TitleDetail') as $detail) {
            $elements = [];
            foreach ($detail->children('TitleElement') as $element) {
                $elements[] = $this->titleElement($element, $this->code(149, $element->text('TitleElementLevel')));
            }
            $details[] = new TitleDetail($this->code(15, $detail->text('TitleType')), $this->sortBySequence($elements), $detail->text('TitleStatement'));
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
            $contributors[] = $this->contributor($element, $this->identifiers($element, 'NameIdentifier', 'NameIDType', 44));
        }

        return $this->sortBySequence($contributors);
    }

    /**
     * @return list<Collection>
     */
    private function collections(Element $descriptive): array
    {
        $collections = [];
        foreach ($descriptive->children('Collection') as $element) {
            $sequences = [];
            foreach ($element->children('CollectionSequence') as $sequence) {
                $number = $sequence->text('CollectionSequenceNumber');
                if (null !== $number) {
                    $sequences[] = new CollectionSequence($this->code(197, $sequence->text('CollectionSequenceType')), $number, $sequence->text('CollectionSequenceTypeName'));
                }
            }
            $titles = $this->titleDetails($element);
            $collections[] = new Collection(
                type: $this->code(148, $element->text('CollectionType')),
                identifiers: $this->identifiers($element, 'CollectionIdentifier', 'CollectionIDType', 13),
                sequences: $sequences,
                titles: $titles,
                contributors: $this->contributors($element),
            );
        }

        // Collection titles may also be given as collection-level title elements in the product's own TitleDetail.
        if ([] === $collections) {
            foreach ($this->titleDetails($descriptive) as $detail) {
                foreach ($detail->elements as $titleElement) {
                    if (null !== $titleElement->level && $titleElement->level->is('02')) {
                        $collections[] = new Collection(type: null, titles: [new TitleDetail($detail->type, [$titleElement])]);
                    }
                }
            }
        }

        return $collections;
    }

    /**
     * @return list<Language>
     */
    private function languages(Element $parent): array
    {
        $languages = [];
        foreach ($parent->children('Language') as $element) {
            $code = $this->code(74, $element->text('LanguageCode'));
            if (null !== $code) {
                $languages[] = new Language($this->code(22, $element->text('LanguageRole')), $code);
            }
        }

        return $languages;
    }

    /**
     * @return list<Extent>
     */
    private function extents(Element $parent): array
    {
        $extents = [];
        foreach ($parent->children('Extent') as $element) {
            $raw = $element->text('ExtentValue');
            $extents[] = new Extent(
                type: $this->code(23, $element->text('ExtentType')),
                value: $this->number($raw, 'ExtentValue'),
                unit: $this->code(24, $element->text('ExtentUnit')),
                rawValue: $raw,
                romanValue: $element->text('ExtentValueRoman'),
            );
        }

        return $extents;
    }

    /**
     * @return list<Subject>
     */
    private function subjects(Element $parent): array
    {
        $subjects = [];
        foreach ($parent->children('Subject') as $element) {
            $subjects[] = $this->subject($element, $element->has('MainSubject'));
        }

        return $subjects;
    }

    /**
     * @return list<TextContent>
     */
    private function texts(Element $collateral): array
    {
        $texts = [];
        foreach ($collateral->children('TextContent') as $element) {
            $type = $this->code(153, $element->text('TextType'));
            $audiences = $this->codes(154, $element, 'ContentAudience');
            foreach ($element->children('Text') as $textElement) {
                $text = $this->text($textElement, $type, null, $audiences);
                if (null !== $text) {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * @return list<SupportingResource>
     */
    private function resources(Element $collateral): array
    {
        $resources = [];
        foreach ($collateral->children('SupportingResource') as $element) {
            $versions = [];
            foreach ($element->children('ResourceVersion') as $version) {
                $link = $version->text('ResourceLink');
                if (null !== $link) {
                    $versions[] = new ResourceVersion($this->code(161, $version->text('ResourceForm')), $link);
                }
            }
            $resources[] = new SupportingResource(
                $this->code(158, $element->text('ResourceContentType')),
                $versions,
                $this->code(159, $element->text('ResourceMode')),
            );
        }

        return $resources;
    }

    /**
     * @return list<Imprint>
     */
    private function imprints(Element $publishing): array
    {
        $imprints = [];
        foreach ($publishing->children('Imprint') as $element) {
            $imprints[] = new Imprint($element->text('ImprintName'), $this->identifiers($element, 'ImprintIdentifier', 'ImprintIDType', 44));
        }

        return $imprints;
    }

    /**
     * @return list<Publisher>
     */
    private function publishers(Element $publishing): array
    {
        $publishers = [];
        foreach ($publishing->children('Publisher') as $element) {
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
                identifiers: $this->identifiers($element, 'PublisherIdentifier', 'PublisherIDType', 44),
                websites: $websites,
            );
        }

        return $publishers;
    }

    /**
     * @return list<ProductDate>
     */
    private function dates(Element $parent, string $composite, string $roleElement, int $roleList): array
    {
        $dates = [];
        foreach ($parent->children($composite) as $element) {
            $dateElement = $element->first('Date');
            $value = $dateElement?->text();
            if (null === $value) {
                continue;
            }
            $format = $dateElement->attribute('dateformat') ?? $element->text('DateFormat');
            $dates[] = new ProductDate($this->code($roleList, $element->text($roleElement)), $value, $this->code(55, $format));
        }

        return $dates;
    }

    /**
     * @return list<SalesRights>
     */
    private function salesRights(Element $publishing): array
    {
        $rights = [];
        foreach ($publishing->children('SalesRights') as $element) {
            $rights[] = new SalesRights($this->code(46, $element->text('SalesRightsType')), $this->territory($element->first('Territory')));
        }

        return $rights;
    }

    private function territory(?Element $territory): Territory
    {
        if (null === $territory) {
            return new Territory();
        }

        return new Territory(
            $this->splitList($territory->text('CountriesIncluded')),
            $this->splitList($territory->text('RegionsIncluded')),
            $this->splitList($territory->text('CountriesExcluded')),
            $this->splitList($territory->text('RegionsExcluded')),
        );
    }

    /**
     * @return list<RelatedWork>
     */
    private function relatedWorks(Element $related): array
    {
        $works = [];
        foreach ($related->children('RelatedWork') as $element) {
            $works[] = new RelatedWork(
                $this->code(164, $element->text('WorkRelationCode')),
                $this->identifiers($element, 'WorkIdentifier', 'WorkIDType', 16),
            );
        }

        return $works;
    }

    /**
     * @return list<RelatedProduct>
     */
    private function relatedProducts(Element $related): array
    {
        $products = [];
        foreach ($related->children('RelatedProduct') as $element) {
            $products[] = new RelatedProduct(
                $this->codes(51, $element, 'ProductRelationCode'),
                $this->identifiers($element, 'ProductIdentifier', 'ProductIDType', 5),
                $this->code(150, $element->text('ProductForm')),
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
        foreach ($product->children('ProductSupply') as $supply) {
            $market = $this->territory($supply->first('Market')?->first('Territory'));
            foreach ($supply->children('SupplyDetail') as $element) {
                $supplier = $element->first('Supplier');
                $details[] = new SupplyDetail(
                    supplierName: $supplier?->text('SupplierName'),
                    supplierRole: $this->code(93, $supplier?->text('SupplierRole')),
                    supplierIdentifiers: null === $supplier ? [] : $this->identifiers($supplier, 'SupplierIdentifier', 'SupplierIDType', 92),
                    availability: $this->code(65, $element->text('ProductAvailability')),
                    dates: $this->dates($element, 'SupplyDate', 'SupplyDateRole', 166),
                    prices: $this->prices($element),
                    market: $market,
                    unpricedItemType: $this->code(57, $element->text('UnpricedItemType')),
                );
            }
        }

        return $details;
    }

    /**
     * @return list<Price>
     */
    private function prices(Element $supplyDetail): array
    {
        $prices = [];
        foreach ($supplyDetail->children('Price') as $element) {
            $prices[] = new Price(
                type: $this->code(58, $element->text('PriceType')),
                amount: $this->number($element->text('PriceAmount'), 'PriceAmount'),
                currencyCode: $element->text('CurrencyCode'),
                territory: $this->territory($element->first('Territory')),
                qualifier: $this->code(59, $element->text('PriceQualifier')),
                taxRatePercent: $this->number($element->first('Tax')?->text('TaxRatePercent'), 'TaxRatePercent'),
                unpricedItemType: $this->code(57, $element->text('UnpricedItemType')),
            );
        }

        return $prices;
    }
}
