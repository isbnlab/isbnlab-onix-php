<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

/**
 * The default: codes are read, but carry no labels. See the README for loading
 * EDItEUR's code lists.
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
