<?php

declare(strict_types=1);

namespace ISBNLab\Onix;

use Closure;
use ISBNLab\Onix\CodeList\BundledCodeLists;
use ISBNLab\Onix\CodeList\CodeLists;

final class ReaderOptions
{
    /**
     * @param CodeLists $codeLists where code labels come from: the EDItEUR code lists shipped with this package by default; NoCodeLists for none
     * @param OnixVersion|null $version force a version instead of detecting it
     * @param bool $throwOnError throw InvalidProductException on the first bad product instead of skipping it
     * @param (Closure(ProductError): void)|null $onError called for every product that is skipped
     * @param int $maxStoredErrors how many skipped products OnixReader::errors() keeps (the count is always exact)
     * @param bool $fixEntities rewrite HTML named entities (&eacute;) that XML does not know
     */
    public function __construct(
        public readonly CodeLists $codeLists = new BundledCodeLists(),
        public readonly ?OnixVersion $version = null,
        public readonly bool $throwOnError = false,
        public readonly ?Closure $onError = null,
        public readonly int $maxStoredErrors = 1000,
        public readonly bool $fixEntities = true,
    ) {}
}
