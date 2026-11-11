<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class RelatedProduct
{
    /**
     * @param list<Code> $relations list 51: 06 alternative format, 13 epublication based on...
     * @param list<Identifier> $identifiers
     */
    public function __construct(
        public readonly array $relations,
        public readonly array $identifiers,
        public readonly ?Code $productForm = null,
    ) {}

    public function hasRelation(string ...$codes): bool
    {
        foreach ($this->relations as $relation) {
            if ($relation->is(...$codes)) {
                return true;
            }
        }

        return false;
    }

    public function isbn13(): ?string
    {
        foreach ($this->identifiers as $identifier) {
            if ($identifier->isType('15')) {
                return $identifier->value;
            }
        }

        return null;
    }
}
