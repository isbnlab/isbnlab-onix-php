<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class TitleDetail
{
    /**
     * @param list<TitleElement> $elements
     */
    public function __construct(
        public readonly ?Code $type,
        public readonly array $elements,
        public readonly ?string $statement = null,
    ) {}

    /**
     * The first title element at the given level (list 149: 01 product, 02 collection),
     * or the first element when none has that level.
     */
    public function element(string $level = '01'): ?TitleElement
    {
        foreach ($this->elements as $element) {
            if (null !== $element->level && $element->level->is($level)) {
                return $element;
            }
        }

        return $this->elements[0] ?? null;
    }
}
