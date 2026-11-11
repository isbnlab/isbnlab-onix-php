<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Price
{
    public function __construct(
        /** List 58: 01 RRP excluding tax, 02 RRP including tax... Null means the header's default price type. */
        public readonly ?Code $type,
        public readonly ?float $amount,
        /** ISO 4217, for example "USD". Null means the header's default currency. */
        public readonly ?string $currencyCode,
        public readonly Territory $territory = new Territory(),
        /** List 59. */
        public readonly ?Code $qualifier = null,
        public readonly ?float $taxRatePercent = null,
        /** List 57: set instead of an amount for free or not-yet-priced items. */
        public readonly ?Code $unpricedItemType = null,
    ) {}
}
