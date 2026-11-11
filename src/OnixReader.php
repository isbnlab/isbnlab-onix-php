<?php

declare(strict_types=1);

namespace ISBNLab\Onix;

use Generator;
use ISBNLab\Onix\Exception\InvalidProductException;
use ISBNLab\Onix\Exception\MalformedXmlException;
use ISBNLab\Onix\Exception\OnixException;
use ISBNLab\Onix\Internal\Element;
use ISBNLab\Onix\Internal\EntityFixer;
use ISBNLab\Onix\Internal\EntityFixFilter;
use ISBNLab\Onix\Model\Header;
use ISBNLab\Onix\Model\Product;
use IteratorAggregate;
use XMLReader;

/**
 * Streams an ONIX for Books message product by product. Memory use stays flat however
 * large the file: only one product is held at a time.
 *
 * Products that cannot be read are skipped and reported (see errors() and
 * ReaderOptions). A file that stops being well-formed XML ends the read with a
 * MalformedXmlException. Products before the fault have been returned by then, except
 * that libxml reads ahead a few kilobytes, so the last products before it may be lost.
 *
 * @implements IteratorAggregate<int, Product>
 */
final class OnixReader implements IteratorAggregate
{
    private readonly ReaderOptions $options;

    private readonly ProductParser $parser;

    private ?OnixVersion $messageVersion = null;

    /** @var list<ProductError> */
    private array $errors = [];

    private int $errorCount = 0;

    private function __construct(
        private readonly ?string $uri,
        private readonly ?string $xml,
        ?ReaderOptions $options,
    ) {
        $this->options = $options ?? new ReaderOptions();
        $this->parser = new ProductParser($this->options);
    }

    /**
     * Opens a file. A path ending in .gz is read through zlib. Any PHP stream URI
     * (compress.zlib://, php://stdin...) can also be given.
     */
    public static function open(string $path, ?ReaderOptions $options = null): self
    {
        if (!str_contains($path, '://')) {
            if (!is_file($path) || !is_readable($path)) {
                throw new OnixException(sprintf('ONIX file not readable: %s', $path));
            }
            if (str_ends_with(strtolower($path), '.gz')) {
                $path = 'compress.zlib://' . $path;
            }
        }

        return new self($path, null, $options);
    }

    public static function fromString(string $xml, ?ReaderOptions $options = null): self
    {
        return new self(null, $xml, $options);
    }

    /**
     * The message header, or null if there is none. Reads only the start of the file.
     */
    public function header(): ?Header
    {
        foreach ($this->walk(true) as [$kind, $xml]) {
            if ('Header' === $kind) {
                try {
                    return $this->parser->parseHeader($xml, $this->messageVersion);
                } catch (InvalidProductException) {
                    return null;
                }
            }

            return null;
        }

        return null;
    }

    /**
     * The version stated by the message (its release attribute or namespace), or
     * null when it states none. Each product's own structure is still checked.
     */
    public function version(): ?OnixVersion
    {
        foreach ($this->walk(true) as $ignored) {
            break;
        }

        return $this->messageVersion;
    }

    /**
     * Every readable product, keyed by its 1-based position in the file.
     *
     * @return Generator<int, Product>
     */
    public function products(): Generator
    {
        $this->errors = [];
        $this->errorCount = 0;
        $index = 0;
        foreach ($this->walk(false) as [$kind, $xml]) {
            if ('Product' !== $kind) {
                continue;
            }
            ++$index;
            try {
                $product = $this->parser->parse($xml, $this->messageVersion, $index);
            } catch (InvalidProductException $e) {
                $this->report($e, $index, $xml);
                continue;
            }
            yield $index => $product;
        }
    }

    /**
     * Each product's XML, unparsed, keyed by its 1-based position: for staging records
     * to parse later with ProductParser.
     *
     * @return Generator<int, string>
     */
    public function rawProducts(): Generator
    {
        $index = 0;
        foreach ($this->walk(false) as [$kind, $xml]) {
            if ('Product' === $kind) {
                yield ++$index => $xml;
            }
        }
    }

    /**
     * @return Generator<int, Product>
     */
    public function getIterator(): Generator
    {
        return $this->products();
    }

    /**
     * Products skipped during the last products() run (up to ReaderOptions::$maxStoredErrors).
     *
     * @return list<ProductError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function errorCount(): int
    {
        return $this->errorCount;
    }

    private function report(InvalidProductException $e, int $index, string $xml): void
    {
        $error = $e->error ?? new ProductError($index, null, $e->getMessage(), $xml);
        ++$this->errorCount;
        if (\count($this->errors) < $this->options->maxStoredErrors) {
            $this->errors[] = $error;
        }
        if (null !== $this->options->onError) {
            ($this->options->onError)($error);
        }
        if ($this->options->throwOnError) {
            throw $e;
        }
    }

    /**
     * Yields [reference name, outer XML] for the Header and each Product.
     *
     * @return Generator<int, array{0: string, 1: string}>
     */
    private function walk(bool $headerOnly): Generator
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = $this->openReader();
        $products = 0;
        try {
            $rootSeen = false;
            $ok = $reader->read();
            while ($ok) {
                if (XMLReader::ELEMENT !== $reader->nodeType) {
                    $ok = $reader->read();
                    continue;
                }
                $name = Element::referenceName($reader->localName);
                if (!$rootSeen) {
                    $rootSeen = true;
                    $this->messageVersion = OnixVersion::fromRelease($reader->getAttribute('release'))
                        ?? self::versionFromNamespace($reader->namespaceURI);
                    if ('Product' !== $name && 'Header' !== $name) {
                        $ok = $reader->read();
                        continue;
                    }
                }
                if ('Header' === $name || 'Product' === $name) {
                    $xml = $reader->readOuterXml();
                    $this->throwOnFatalError($products);
                    if ('Product' === $name) {
                        ++$products;
                    }
                    yield [$name, $xml];
                    if ($headerOnly) {
                        return;
                    }
                    libxml_clear_errors();
                    $ok = $reader->next();
                    continue;
                }
                $ok = $reader->read();
            }
            $this->throwOnFatalError($products);
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * XMLReader reports a fatal error and then carries on with repaired content, so
     * errors are checked after every step rather than only when reading stops.
     */
    private function throwOnFatalError(int $products): void
    {
        foreach (libxml_get_errors() as $error) {
            if ($error->level >= \LIBXML_ERR_ERROR) {
                throw new MalformedXmlException(
                    sprintf('Malformed XML at line %d: %s (%d products read before it)', $error->line, trim($error->message), $products),
                    $error->line,
                    $products,
                );
            }
        }
    }

    private function openReader(): XMLReader
    {
        $flags = \LIBXML_NONET | \LIBXML_COMPACT;
        if (null !== $this->xml) {
            $reader = XMLReader::XML($this->options->fixEntities ? EntityFixer::fix($this->xml) : $this->xml, null, $flags);
        } else {
            $uri = (string) $this->uri;
            if ($this->options->fixEntities) {
                EntityFixFilter::register();
                $uri = 'php://filter/read=' . EntityFixFilter::NAME . '/resource=' . $uri;
            }
            $reader = @XMLReader::open($uri, null, $flags);
        }
        if (!$reader instanceof XMLReader) {
            throw new OnixException(sprintf('Could not open ONIX source: %s', $this->uri ?? 'string'));
        }

        return $reader;
    }

    private static function versionFromNamespace(?string $namespace): ?OnixVersion
    {
        return match (true) {
            null === $namespace => null,
            str_contains($namespace, '/2.1/') => OnixVersion::V21,
            str_contains($namespace, '/3.1/') => OnixVersion::V31,
            str_contains($namespace, '/3.0/') => OnixVersion::V30,
            default => null,
        };
    }
}
