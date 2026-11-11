<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Measure
{
    public function __construct(
        /** List 48: 01 height, 02 width, 03 thickness, 08 weight. */
        public readonly ?Code $type,
        public readonly ?float $value,
        /** List 50: cm, mm, in, gr, kg, lb, oz. */
        public readonly ?Code $unit,
        public readonly ?string $rawValue = null,
    ) {}
}
