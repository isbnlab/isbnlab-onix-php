<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class SalesRights
{
    public function __construct(
        /** List 46: 01 for sale with exclusive rights, 03 not for sale... */
        public readonly ?Code $type,
        public readonly Territory $territory,
    ) {}
}
