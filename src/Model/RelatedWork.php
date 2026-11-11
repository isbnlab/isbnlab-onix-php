<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class RelatedWork
{
    /**
     * @param list<Identifier> $identifiers list 16
     */
    public function __construct(
        /** List 164: 01 "manifestation of" is the work this product belongs to. */
        public readonly ?Code $relation,
        public readonly array $identifiers,
    ) {}
}
