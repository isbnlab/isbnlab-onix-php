<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

/**
 * A cover image, sample, video or other linked resource (ONIX 3.0 SupportingResource,
 * ONIX 2.1 MediaFile).
 */
final class SupportingResource
{
    /**
     * @param list<ResourceVersion> $versions
     */
    public function __construct(
        /** ONIX 3.0: list 158 (01 front cover). ONIX 2.1: list 38 (04 front cover image). */
        public readonly ?Code $contentType,
        public readonly array $versions,
        /** List 159: 03 image, 04 audio, 05 video... Null in ONIX 2.1. */
        public readonly ?Code $mode = null,
    ) {}

    public function link(): ?string
    {
        return ($this->versions[0] ?? null)?->link;
    }
}
