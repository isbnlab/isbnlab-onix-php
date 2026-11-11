<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Subject
{
    public function __construct(
        /** List 27: 10 BISAC, 12 BIC, 20 keywords, 93 Thema subject... */
        public readonly ?Code $scheme,
        public readonly ?string $code = null,
        public readonly ?string $headingText = null,
        public readonly bool $main = false,
        public readonly ?string $schemeName = null,
        public readonly ?string $schemeVersion = null,
    ) {}

    public function isScheme(string ...$schemes): bool
    {
        return null !== $this->scheme && $this->scheme->is(...$schemes);
    }
}
