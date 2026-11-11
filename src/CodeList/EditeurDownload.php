<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

use ISBNLab\Onix\Exception\OnixException;

/**
 * Downloads the current issue of the code lists from EDItEUR's code lists page,
 * in JSON. Used by bin/fetch-codelists to refresh data/codelists, and usable to
 * get a newer issue than the one this package ships.
 */
final class EditeurDownload
{
    public const PAGE_URL = 'https://www.editeur.org/14/Code-Lists/';

    /**
     * The newest issue's JSON link on the code lists page, as [issue, absolute URL].
     *
     * @return array{int, string}
     */
    public static function latestJsonUrl(string $pageHtml, string $pageUrl = self::PAGE_URL): array
    {
        preg_match_all('#href="([^"]*/ONIX_BookProduct_Codelists_Issue_(\d+)\.json)"#i', $pageHtml, $matches, \PREG_SET_ORDER);
        if ([] === $matches) {
            throw new OnixException('No code list JSON link found on ' . $pageUrl);
        }
        usort($matches, static fn(array $a, array $b): int => (int) $b[2] <=> (int) $a[2]);
        [, $href, $issue] = $matches[0];

        $href = html_entity_decode($href, \ENT_QUOTES | \ENT_HTML5);
        if (!str_starts_with($href, 'http')) {
            $parts = parse_url($pageUrl);
            $href = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.editeur.org') . '/' . ltrim($href, '/');
        }

        return [(int) $issue, str_replace(' ', '%20', $href)];
    }

    /**
     * Downloads the newest issue into $directory, checks that it reads as code lists,
     * and returns [issue, path of the saved file]. The file is saved exactly as
     * EDItEUR publishes it.
     *
     * @return array{int, string}
     */
    public static function latestInto(string $directory): array
    {
        [$issue, $url] = self::latestJsonUrl(self::get(self::PAGE_URL));
        $json = self::get($url);

        $lists = EditeurCodeLists::fromJson($json);
        if ($lists->issue() !== $issue) {
            throw new OnixException(sprintf('%s says issue %s, expected %d', $url, var_export($lists->issue(), true), $issue));
        }

        $path = rtrim($directory, '/') . '/' . basename(rawurldecode((string) parse_url($url, \PHP_URL_PATH)));
        if (false === @file_put_contents($path, $json)) {
            throw new OnixException(sprintf('Could not write %s', $path));
        }

        return [$issue, $path];
    }

    private static function get(string $url): string
    {
        $context = stream_context_create(['http' => [
            'user_agent' => 'isbnlab-onix (https://github.com/isbnlab/isbnlab-onix-php)',
            'timeout' => 60,
            'follow_location' => 1,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if (false === $body || '' === $body) {
            throw new OnixException(sprintf('Could not download %s', $url));
        }

        return $body;
    }
}
