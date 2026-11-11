<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

/**
 * A series or other collection (ONIX 3.0 Collection; ONIX 2.1 Series and Set).
 */
final class Collection
{
    /**
     * @param list<Identifier> $identifiers
     * @param list<CollectionSequence> $sequences
     * @param list<TitleDetail> $titles
     * @param list<Contributor> $contributors
     */
    public function __construct(
        /** List 148: 10 publisher collection, 20 ascribed collection. Null in ONIX 2.1. */
        public readonly ?Code $type,
        public readonly array $identifiers = [],
        public readonly array $sequences = [],
        public readonly array $titles = [],
        public readonly array $contributors = [],
    ) {}

    public function title(): ?string
    {
        foreach ($this->titles as $detail) {
            $title = $detail->element('02')?->title();
            if (null !== $title) {
                return $title;
            }
        }

        return null;
    }

    /**
     * The product's number within the collection: the first CollectionSequence, else
     * the collection-level PartNumber (ONIX 2.1 NumberWithinSeries).
     */
    public function partNumber(): ?string
    {
        if ([] !== $this->sequences) {
            return $this->sequences[0]->number;
        }
        foreach ($this->titles as $detail) {
            $partNumber = $detail->element('02')?->partNumber;
            if (null !== $partNumber) {
                return $partNumber;
            }
        }

        return null;
    }
}
