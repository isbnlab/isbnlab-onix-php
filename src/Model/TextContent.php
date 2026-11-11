<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

/**
 * A description, table of contents, review or other text (ONIX 3.0 TextContent,
 * ONIX 2.1 OtherText).
 */
final class TextContent
{
    /**
     * @param list<Code> $audiences list 154
     */
    public function __construct(
        /** ONIX 3.0: list 153 (03 description, 02 short description). ONIX 2.1: list 33 (01 main description). */
        public readonly ?Code $type,
        /** The text as given; may contain XHTML or escaped HTML, see $format. */
        public readonly string $text,
        /** The textformat attribute (list 34): 02 HTML, 05 XHTML, 06 default text format... */
        public readonly ?string $format = null,
        public readonly ?string $language = null,
        public readonly array $audiences = [],
    ) {}

    /**
     * The text with markup removed and whitespace collapsed.
     */
    public function plainText(): string
    {
        $text = preg_replace('/<(br|p|div|li|h[1-6])\b[^>]*>/i', ' ', $this->text) ?? $this->text;
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
