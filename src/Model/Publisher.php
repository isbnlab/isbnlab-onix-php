<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Publisher
{
    /**
     * @param list<Identifier> $identifiers
     * @param list<string> $websites
     */
    public function __construct(
        public readonly ?string $name,
        /** List 45: 01 publisher, 02 co-publisher... */
        public readonly ?Code $role = null,
        public readonly array $identifiers = [],
        public readonly array $websites = [],
    ) {}
}
