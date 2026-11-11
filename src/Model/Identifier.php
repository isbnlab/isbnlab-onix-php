<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

/**
 * Any ONIX identifier composite: product, work, collection, name, publisher, imprint,
 * supplier or sender identifier.
 */
final class Identifier
{
    public function __construct(
        public readonly ?Code $type,
        public readonly string $value,
        /** The scheme name given with proprietary identifiers (IDTypeName). */
        public readonly ?string $typeName = null,
    ) {}

    public function isType(string ...$types): bool
    {
        return null !== $this->type && $this->type->is(...$types);
    }
}
