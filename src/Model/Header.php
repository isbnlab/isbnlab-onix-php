<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

use ISBNLab\Onix\OnixVersion;

final class Header
{
    /**
     * @param list<Identifier> $senderIdentifiers
     * @param list<string> $addresseeNames
     * @param list<string> $messageNotes
     */
    public function __construct(
        public readonly ?OnixVersion $version,
        public readonly ?string $senderName,
        public readonly ?string $contactName = null,
        public readonly ?string $emailAddress = null,
        public readonly array $senderIdentifiers = [],
        public readonly array $addresseeNames = [],
        public readonly ?string $messageNumber = null,
        /** As given: "20240315", "20240315T1030+0000"... */
        public readonly ?string $sentDateTime = null,
        public readonly array $messageNotes = [],
        public readonly ?string $defaultLanguageOfText = null,
        public readonly ?string $defaultCurrencyCode = null,
        public readonly ?Code $defaultPriceType = null,
    ) {}
}
