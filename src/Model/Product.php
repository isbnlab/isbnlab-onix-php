<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

use ISBNLab\Onix\OnixVersion;

/**
 * One ONIX product record, read-only. ONIX 2.1 records are mapped onto the same shape
 * as ONIX 3.0 ones; each property says where its codes come from.
 */
final class Product
{
    /**
     * @param list<Identifier> $identifiers list 5
     * @param list<Code> $productFormDetails list 175 (2.1: list 78)
     * @param list<string> $productFormDescriptions
     * @param list<Measure> $measures
     * @param list<Collection> $collections
     * @param list<TitleDetail> $titles
     * @param list<Contributor> $contributors
     * @param list<string> $contributorStatements
     * @param list<Code> $editionTypes list 21
     * @param list<Language> $languages
     * @param list<Extent> $extents
     * @param list<Subject> $subjects
     * @param list<TextContent> $texts
     * @param list<SupportingResource> $resources
     * @param list<Imprint> $imprints
     * @param list<Publisher> $publishers
     * @param list<string> $citiesOfPublication
     * @param list<ProductDate> $publishingDates list 163
     * @param list<SalesRights> $salesRights
     * @param list<RelatedWork> $relatedWorks
     * @param list<RelatedProduct> $relatedProducts
     * @param list<SupplyDetail> $supplyDetails
     * @param list<string> $warnings values that were present but could not be read
     */
    public function __construct(
        public readonly OnixVersion $version,
        public readonly ?string $recordReference,
        /** List 1: 03 confirmed, 04 update, 05 delete... */
        public readonly ?Code $notificationType = null,
        public readonly array $identifiers = [],
        public readonly ?string $deletionText = null,
        public readonly ?string $recordSourceName = null,
        /** List 2. */
        public readonly ?Code $productComposition = null,
        /** List 150 (2.1: list 7, labelled from list 150). */
        public readonly ?Code $productForm = null,
        public readonly array $productFormDetails = [],
        public readonly array $productFormDescriptions = [],
        public readonly array $measures = [],
        public readonly array $collections = [],
        public readonly bool $noCollection = false,
        public readonly array $titles = [],
        public readonly array $contributors = [],
        public readonly array $contributorStatements = [],
        public readonly bool $noContributor = false,
        public readonly array $editionTypes = [],
        public readonly ?int $editionNumber = null,
        public readonly ?string $editionStatement = null,
        public readonly array $languages = [],
        public readonly array $extents = [],
        /** ONIX 2.1 NumberOfPages. ONIX 3.0 page counts are extents; use pageCount(). */
        public readonly ?int $numberOfPages = null,
        public readonly array $subjects = [],
        public readonly array $texts = [],
        public readonly array $resources = [],
        public readonly array $imprints = [],
        public readonly array $publishers = [],
        public readonly array $citiesOfPublication = [],
        public readonly ?string $countryOfPublication = null,
        /** List 64: 02 forthcoming, 04 active, 07 out of print... */
        public readonly ?Code $publishingStatus = null,
        public readonly array $publishingDates = [],
        public readonly array $salesRights = [],
        public readonly array $relatedWorks = [],
        public readonly array $relatedProducts = [],
        public readonly array $supplyDetails = [],
        /** 1-based position in the file, when read with OnixReader. */
        public readonly ?int $index = null,
        public readonly array $warnings = [],
    ) {}

    // Identifiers

    /**
     * The value of the first identifier of one of the given types (list 5).
     */
    public function identifier(string ...$types): ?string
    {
        foreach ($types as $type) {
            foreach ($this->identifiers as $identifier) {
                if ($identifier->isType($type)) {
                    return $identifier->value;
                }
            }
        }

        return null;
    }

    public function isbn13(): ?string
    {
        return $this->identifier('15');
    }

    public function isbn10(): ?string
    {
        return $this->identifier('02');
    }

    public function gtin13(): ?string
    {
        return $this->identifier('03');
    }

    /**
     * @return list<Identifier>
     */
    public function proprietaryIdentifiers(): array
    {
        return array_values(array_filter($this->identifiers, static fn(Identifier $id): bool => $id->isType('01')));
    }

    // Titles and contributors

    /**
     * The distinctive title (title type 01, product level), else the first title.
     */
    public function titleElement(): ?TitleElement
    {
        foreach ($this->titles as $detail) {
            if (null !== $detail->type && $detail->type->is('01')) {
                return $detail->element('01');
            }
        }

        return ($this->titles[0] ?? null)?->element('01');
    }

    public function title(): ?string
    {
        return $this->titleElement()?->title();
    }

    public function subtitle(): ?string
    {
        return $this->titleElement()?->subtitle;
    }

    /**
     * Contributors with one of the given roles (list 17), in sequence order. With no
     * roles given: authors (A01).
     *
     * @return list<Contributor>
     */
    public function contributorsWithRole(string ...$roles): array
    {
        $roles = [] === $roles ? ['A01'] : $roles;

        return array_values(array_filter($this->contributors, static fn(Contributor $c): bool => $c->hasRole(...$roles)));
    }

    /**
     * @return list<Contributor>
     */
    public function authors(): array
    {
        return $this->contributorsWithRole('A01');
    }

    // Collections

    /**
     * The series: the first publisher collection (type 10), else the first collection
     * of any type.
     */
    public function series(): ?Collection
    {
        foreach ($this->collections as $collection) {
            if (null !== $collection->type && $collection->type->is('10')) {
                return $collection;
            }
        }

        return $this->collections[0] ?? null;
    }

    // Physical and extent

    /**
     * The page count: ONIX 2.1 NumberOfPages, else the first page extent in order of
     * preference (main content, content, total numbered, absolute, print counterpart,
     * notional).
     */
    public function pageCount(): ?int
    {
        if (null !== $this->numberOfPages && $this->numberOfPages > 0) {
            return $this->numberOfPages;
        }
        foreach (['00', '11', '05', '07', '08', '10'] as $type) {
            foreach ($this->extents as $extent) {
                if (null !== $extent->type && $extent->type->is($type) && null !== $extent->value && $extent->value > 0
                    && (null === $extent->unit || $extent->unit->is('03'))) {
                    return (int) $extent->value;
                }
            }
        }

        return null;
    }

    /**
     * The first measure of the given type (list 48: 01 height, 02 width, 03 thickness,
     * 08 weight), optionally in a given unit.
     */
    public function measure(string $type, ?string $unit = null): ?Measure
    {
        foreach ($this->measures as $measure) {
            if (null !== $measure->type && $measure->type->is($type)
                && (null === $unit || (null !== $measure->unit && $measure->unit->is($unit)))) {
                return $measure;
            }
        }

        return null;
    }

    /**
     * The language of the text (role 01) as an ISO 639-2/B code, else the first language.
     */
    public function languageCode(): ?string
    {
        foreach ($this->languages as $language) {
            if (null !== $language->role && $language->role->is('01')) {
                return $language->code->value;
            }
        }

        return ($this->languages[0] ?? null)?->code->value;
    }

    // Subjects

    /**
     * @return list<Subject>
     */
    public function subjectsInScheme(string ...$schemes): array
    {
        return array_values(array_filter($this->subjects, static fn(Subject $s): bool => $s->isScheme(...$schemes)));
    }

    /**
     * BISAC subject codes, main subject first.
     *
     * @return list<string>
     */
    public function bisacCodes(): array
    {
        return $this->codesInScheme('10');
    }

    /**
     * Thema subject category codes, main subject first.
     *
     * @return list<string>
     */
    public function themaCodes(): array
    {
        return $this->codesInScheme('93');
    }

    /**
     * Keywords (scheme 20), split on semicolons, or on commas when there are none.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        $keywords = [];
        foreach ($this->subjectsInScheme('20') as $subject) {
            $text = $subject->headingText ?? $subject->code ?? '';
            $separator = str_contains($text, ';') ? ';' : ',';
            foreach (explode($separator, $text) as $keyword) {
                $keyword = trim($keyword);
                if ('' !== $keyword) {
                    $keywords[$keyword] = true;
                }
            }
        }

        return array_keys($keywords);
    }

    // Texts

    /**
     * Texts of one of the given types, in the order given.
     *
     * @return list<TextContent>
     */
    public function textsOfType(string ...$types): array
    {
        $texts = [];
        foreach ($types as $type) {
            foreach ($this->texts as $text) {
                if (null !== $text->type && $text->type->is($type)) {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * The main description: ONIX 3.0 type 03, else short description 02. ONIX 2.1:
     * main description 01, else long 03, else short 02.
     */
    public function description(): ?TextContent
    {
        $types = $this->version->isOnix3() ? ['03', '02'] : ['01', '03', '02'];

        return $this->textsOfType(...$types)[0] ?? null;
    }

    // Publishing

    public function publisherName(): ?string
    {
        foreach ($this->publishers as $publisher) {
            if (null !== $publisher->name && (null === $publisher->role || $publisher->role->is('01'))) {
                return $publisher->name;
            }
        }
        foreach ($this->publishers as $publisher) {
            if (null !== $publisher->name) {
                return $publisher->name;
            }
        }

        return null;
    }

    public function imprintName(): ?string
    {
        foreach ($this->imprints as $imprint) {
            if (null !== $imprint->name) {
                return $imprint->name;
            }
        }

        return null;
    }

    public function publishingDate(string $role): ?ProductDate
    {
        foreach ($this->publishingDates as $date) {
            if (null !== $date->role && $date->role->is($role)) {
                return $date;
            }
        }

        return null;
    }

    /**
     * The publication date (role 01).
     */
    public function publicationDate(): ?ProductDate
    {
        return $this->publishingDate('01');
    }

    // Related material

    /**
     * Identifiers of the work this product is a manifestation of (relation 01; ONIX
     * 2.1 WorkIdentifier).
     *
     * @return list<Identifier>
     */
    public function workIdentifiers(): array
    {
        $identifiers = [];
        foreach ($this->relatedWorks as $work) {
            if (null === $work->relation || $work->relation->is('01')) {
                array_push($identifiers, ...$work->identifiers);
            }
        }

        return $identifiers;
    }

    // Resources

    /**
     * The front cover image link. ONIX 3.0: content type 01. ONIX 2.1: media file
     * type 04 (front cover image), else 06 (high quality front cover).
     */
    public function frontCoverUrl(): ?string
    {
        $types = $this->version->isOnix3() ? ['01'] : ['04', '06'];
        foreach ($types as $type) {
            foreach ($this->resources as $resource) {
                if (null !== $resource->contentType && $resource->contentType->is($type) && null !== $resource->link()) {
                    return $resource->link();
                }
            }
        }

        return null;
    }

    // Supply

    /**
     * Every price across every supply detail.
     *
     * @return list<Price>
     */
    public function prices(): array
    {
        $prices = [];
        foreach ($this->supplyDetails as $supplyDetail) {
            array_push($prices, ...$supplyDetail->prices);
        }

        return $prices;
    }

    /**
     * The first price in a currency, optionally of given price types (list 58).
     */
    public function price(string $currencyCode, string ...$types): ?Price
    {
        foreach ($this->prices() as $price) {
            if ($price->currencyCode === $currencyCode && null !== $price->amount
                && ([] === $types || (null !== $price->type && $price->type->is(...$types)))) {
                return $price;
            }
        }

        return null;
    }

    public function isDeleted(): bool
    {
        return null !== $this->notificationType && $this->notificationType->is('05');
    }

    /**
     * @return list<string>
     */
    private function codesInScheme(string $scheme): array
    {
        $codes = [];
        $subjects = $this->subjectsInScheme($scheme);
        usort($subjects, static fn(Subject $a, Subject $b): int => (int) $b->main <=> (int) $a->main);
        foreach ($subjects as $subject) {
            if (null !== $subject->code) {
                $codes[$subject->code] = true;
            }
        }

        return array_map('strval', array_keys($codes));
    }
}
