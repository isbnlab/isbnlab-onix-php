<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

/**
 * No labels: codes are read with their list number and value only. Pass it in
 * ReaderOptions to skip loading the bundled code lists.
 */
final class NoCodeLists implements CodeLists
{
    public function label(int $list, string $value): ?string
    {
        return null;
    }

    public function issue(): ?int
    {
        return null;
    }
}
