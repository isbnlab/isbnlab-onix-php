<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

/**
 * Looks up the label of a code in an EDItEUR ONIX code list.
 */
interface CodeLists
{
    /**
     * The label (EDItEUR's "description") for $value in list $list, or null if unknown.
     */
    public function label(int $list, string $value): ?string;

    /**
     * The EDItEUR code list issue number, if known.
     */
    public function issue(): ?int;
}
