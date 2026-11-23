<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Imprint
{
    /**
     * @param list<Identifier> $identifiers
     */
    public function __construct(
        /** ImprintName, or ImprintNameInverted when that is the only form given (ONIX 3.1.3). */
        public readonly ?string $name,
        public readonly array $identifiers = [],
        /** ImprintNameInverted (ONIX 3.1.3), for sorting. */
        public readonly ?string $nameInverted = null,
    ) {}
}
