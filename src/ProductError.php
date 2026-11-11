<?php

declare(strict_types=1);

namespace ISBNLab\Onix;

/**
 * A product that could not be read and was skipped.
 */
final class ProductError
{
    public function __construct(
        /** 1-based position of the product in the file. */
        public readonly ?int $index,
        public readonly ?string $recordReference,
        public readonly string $message,
        /** The product's XML as read from the file. */
        public readonly string $xml,
    ) {}
}
