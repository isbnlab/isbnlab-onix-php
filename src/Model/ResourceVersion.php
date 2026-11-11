<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class ResourceVersion
{
    public function __construct(
        /** ONIX 3.0: list 161 (01 linkable, 02 downloadable). ONIX 2.1: list 40 (MediaFileLinkTypeCode). */
        public readonly ?Code $form,
        public readonly string $link,
        /** ONIX 2.1 MediaFileFormatCode (list 39). */
        public readonly ?Code $fileFormat = null,
    ) {}
}
