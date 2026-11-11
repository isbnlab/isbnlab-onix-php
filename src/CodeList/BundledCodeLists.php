<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

use ISBNLab\Onix\Exception\OnixException;

/**
 * The EDItEUR code lists shipped in this package's data/codelists folder: the
 * default, so codes carry labels out of the box.
 *
 * The file is read the first time a label is needed and then kept for the rest of
 * the process, however many readers are opened.
 */
final class BundledCodeLists implements CodeLists
{
    public const DIRECTORY = __DIR__ . '/../../data/codelists';

    private static ?ArrayCodeLists $loaded = null;

    public function label(int $list, string $value): ?string
    {
        return self::lists()->label($list, $value);
    }

    public function issue(): ?int
    {
        return self::lists()->issue();
    }

    /**
     * The bundled JSON file with the highest issue number.
     */
    public static function path(): string
    {
        $latest = null;
        $latestIssue = -1;
        $files = glob(self::DIRECTORY . '/ONIX_BookProduct_Codelists_Issue_*.json');
        foreach (false === $files ? [] : $files as $file) {
            if (1 === preg_match('/_Issue_(\d+)\.json$/', $file, $match) && (int) $match[1] > $latestIssue) {
                $latest = $file;
                $latestIssue = (int) $match[1];
            }
        }
        if (null === $latest) {
            throw new OnixException(sprintf('No bundled code lists in %s', self::DIRECTORY));
        }

        return $latest;
    }

    private static function lists(): ArrayCodeLists
    {
        return self::$loaded ??= EditeurCodeLists::fromFile(self::path());
    }
}
