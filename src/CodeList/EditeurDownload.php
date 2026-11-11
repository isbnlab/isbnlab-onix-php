<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

use ISBNLab\Onix\Exception\OnixException;

/**
 * Downloads the current issue of the code lists from EDItEUR's code lists page,
 * in JSON, for this package's maintainers (bin/fetch-codelists). Users get the
 * code lists with the package, so they never need to fetch from EDItEUR.
 *
 * EDItEUR asks that a new issue is copied only once it has been out for a week or
 * two, in case it needs a correction, so a download refuses a newer issue than that.
 *
 * @internal
 */
final class EditeurDownload
{
    public const PAGE_URL = 'https://www.editeur.org/14/Code-Lists/';

    /** How long a new issue must have been published before it is copied. */
    public const MIN_AGE_DAYS = 14;

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
     * True once an issue published at $released is old enough to copy. An unknown
     * date counts as not yet settled.
     */
    public static function isSettled(?\DateTimeImmutable $released, \DateTimeImmutable $now, int $minAgeDays = self::MIN_AGE_DAYS): bool
    {
        return null !== $released && $released <= $now->modify(sprintf('-%d days', $minAgeDays));
    }

    /**
     * The newest issue on EDItEUR's page: [issue, URL, publication time from the
     * file's Last-Modified header, or null if the server gives none].
     *
     * @return array{int, string, \DateTimeImmutable|null}
     */
    public static function latest(): array
    {
        [$issue, $url] = self::latestJsonUrl(self::get(self::PAGE_URL));

        return [$issue, $url, self::lastModified($url)];
    }

    /**
     * Downloads issue $url into $directory, checks that it reads as that issue of the
     * code lists, and returns the path of the saved file. The file is saved exactly
     * as EDItEUR publishes it.
     */
    public static function downloadInto(string $url, int $issue, string $directory): string
    {
        $json = self::get($url);

        $lists = EditeurCodeLists::fromJson($json);
        if ($lists->issue() !== $issue) {
            throw new OnixException(sprintf('%s says issue %s, expected %d', $url, var_export($lists->issue(), true), $issue));
        }

        $path = rtrim($directory, '/') . '/' . basename(rawurldecode((string) parse_url($url, \PHP_URL_PATH)));
        if (false === @file_put_contents($path, $json)) {
            throw new OnixException(sprintf('Could not write %s', $path));
        }

        return $path;
    }

    private static function lastModified(string $url): ?\DateTimeImmutable
    {
        $headers = @get_headers($url, true, self::context('HEAD'));
        if (false === $headers) {
            return null;
        }
        $value = array_change_key_case($headers)['last-modified'] ?? null;
        if (\is_array($value)) {
            $value = end($value);
        }
        if (!\is_string($value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat(\DATE_RFC7231, $value);

        return false === $date ? null : $date;
    }

    /**
     * @return resource
     */
    private static function context(string $method = 'GET')
    {
        return stream_context_create(['http' => [
            'method' => $method,
            'user_agent' => 'isbnlab-onix maintainers (https://github.com/isbnlab/isbnlab-onix-php)',
            'timeout' => 60,
            'follow_location' => 1,
        ]]);
    }

    private static function get(string $url): string
    {
        $body = @file_get_contents($url, false, self::context());
        if (false === $body || '' === $body) {
            throw new OnixException(sprintf('Could not download %s', $url));
        }

        return $body;
    }
}
