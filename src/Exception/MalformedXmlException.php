<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Exception;

/**
 * The file stops being well-formed XML, so reading cannot continue past this point.
 * Products before it have already been returned.
 */
final class MalformedXmlException extends OnixException
{
    public function __construct(
        string $message,
        public readonly ?int $xmlLine = null,
        public readonly int $productsRead = 0,
    ) {
        parent::__construct($message);
    }
}
