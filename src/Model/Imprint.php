<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Imprint
{
    /**
     * @param list<Identifier> $identifiers
     */
    public function __construct(
        public readonly ?string $name,
        public readonly array $identifiers = [],
    ) {}
}
