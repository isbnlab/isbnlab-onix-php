<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Language
{
    public function __construct(
        /** List 22: 01 language of text, 02 original language of a translated text. */
        public readonly ?Code $role,
        /** List 74: ISO 639-2/B code such as "eng". */
        public readonly Code $code,
    ) {}
}
