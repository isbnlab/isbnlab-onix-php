<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

/**
 * Real ONIX feeds, especially ONIX 2.1 ones that relied on the DTD, use HTML named
 * entities such as &eacute; or &mdash;. Without the DTD those are fatal XML errors, so
 * they are rewritten as numeric character references. Unknown names are escaped so the
 * text survives as written.
 *
 * @internal
 */
final class EntityFixer
{
    private const PATTERN = '/&(?!(?:amp|lt|gt|quot|apos);)([A-Za-z][A-Za-z0-9]{1,31});/';

    /** @var array<string, string>|null */
    private static ?array $table = null;

    public static function fix(string $xml): string
    {
        if (!str_contains($xml, '&')) {
            return $xml;
        }

        return (string) preg_replace_callback(self::PATTERN, static function (array $match): string {
            $codePoint = self::table()[$match[1]] ?? null;

            return null === $codePoint ? '&amp;' . $match[1] . ';' : $codePoint;
        }, $xml);
    }

    /**
     * @return array<string, string> entity name => numeric character reference
     */
    private static function table(): array
    {
        if (null !== self::$table) {
            return self::$table;
        }
        self::$table = [];
        foreach (get_html_translation_table(\HTML_ENTITIES, \ENT_QUOTES | \ENT_HTML5, 'UTF-8') as $character => $entity) {
            $name = substr($entity, 1, -1);
            self::$table[$name] = self::numericReferences($character);
        }

        return self::$table;
    }

    private static function numericReferences(string $utf8): string
    {
        $references = '';
        $length = \strlen($utf8);
        for ($i = 0; $i < $length;) {
            $byte = \ord($utf8[$i]);
            [$bytes, $codePoint] = match (true) {
                $byte < 0x80 => [1, $byte],
                $byte < 0xE0 => [2, $byte & 0x1F],
                $byte < 0xF0 => [3, $byte & 0x0F],
                default => [4, $byte & 0x07],
            };
            for ($j = 1; $j < $bytes && $i + $j < $length; ++$j) {
                $codePoint = ($codePoint << 6) | (\ord($utf8[$i + $j]) & 0x3F);
            }
            $references .= '&#' . $codePoint . ';';
            $i += $bytes;
        }

        return $references;
    }
}
