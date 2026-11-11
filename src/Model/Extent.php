<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Extent
{
    public function __construct(
        /** List 23: 00 main content page count, 05 total numbered pages, 09 duration... */
        public readonly ?Code $type,
        public readonly ?float $value,
        /** List 24: 03 pages, 05 minutes... */
        public readonly ?Code $unit,
        public readonly ?string $rawValue = null,
        public readonly ?string $romanValue = null,
    ) {}
}
