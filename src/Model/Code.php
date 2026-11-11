<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

use Stringable;

/**
 * A value from an EDItEUR code list, with its label.
 */
final class Code implements Stringable
{
    public function __construct(
        /** EDItEUR code list number, for example 5 for product identifier types. */
        public readonly int $list,
        /** The code as it appears in the feed, for example "15". */
        public readonly string $value,
        /** EDItEUR's description of the code, or null if the code is not in the code lists used. */
        public readonly ?string $label = null,
    ) {}

    public function is(string ...$values): bool
    {
        return \in_array($this->value, $values, true);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
