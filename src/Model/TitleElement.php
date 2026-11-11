<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class TitleElement
{
    public function __construct(
        public readonly ?Code $level,
        public readonly ?string $text,
        public readonly ?string $prefix = null,
        public readonly ?string $withoutPrefix = null,
        public readonly ?string $subtitle = null,
        public readonly ?string $partNumber = null,
        public readonly ?string $yearOfAnnual = null,
        public readonly ?int $sequenceNumber = null,
    ) {}

    /**
     * The full title text: TitleText, or TitlePrefix + TitleWithoutPrefix.
     */
    public function title(): ?string
    {
        if (null !== $this->text) {
            return $this->text;
        }
        $title = trim(($this->prefix ?? '') . ' ' . ($this->withoutPrefix ?? ''));

        return '' === $title ? null : $title;
    }
}
