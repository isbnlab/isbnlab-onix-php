<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Exception;

use ISBNLab\Onix\ProductError;
use Throwable;

/**
 * One product could not be read. Thrown by ProductParser, and by OnixReader when
 * ReaderOptions::$throwOnError is set; otherwise the reader reports and skips it.
 */
final class InvalidProductException extends OnixException
{
    public function __construct(
        string $message,
        public readonly ?ProductError $error = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
