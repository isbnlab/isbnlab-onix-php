<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class CollectionSequence
{
    public function __construct(
        /** List 197. */
        public readonly ?Code $type,
        public readonly string $number,
        public readonly ?string $typeName = null,
    ) {}
}
