<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class SupplyDetail
{
    /**
     * @param list<Identifier> $supplierIdentifiers
     * @param list<ProductDate> $dates list 166 roles
     * @param list<Price> $prices
     */
    public function __construct(
        public readonly ?string $supplierName,
        /** List 93. */
        public readonly ?Code $supplierRole = null,
        public readonly array $supplierIdentifiers = [],
        /** List 65 (ProductAvailability); in ONIX 2.1 without it, list 54 (AvailabilityCode). */
        public readonly ?Code $availability = null,
        public readonly array $dates = [],
        public readonly array $prices = [],
        /** ONIX 3.0: the Market this supply detail belongs to. */
        public readonly Territory $market = new Territory(),
        public readonly ?Code $unpricedItemType = null,
    ) {}
}
