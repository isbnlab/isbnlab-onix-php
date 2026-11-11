<?php

declare(strict_types=1);

namespace ISBNLab\Onix;

use DOMDocument;
use DOMElement;
use ISBNLab\Onix\Exception\InvalidProductException;
use ISBNLab\Onix\Internal\Element;
use ISBNLab\Onix\Internal\EntityFixer;
use ISBNLab\Onix\Internal\HeaderMapper;
use ISBNLab\Onix\Internal\Onix21Mapper;
use ISBNLab\Onix\Internal\Onix30Mapper;
use ISBNLab\Onix\Model\Header;
use ISBNLab\Onix\Model\Product;
use Throwable;

/**
 * Parses a single product from XML: a Product element on its own (as stored by a feed
 * splitter, or returned by a lookup API) or a whole ONIX message, of which the first
 * product is taken. Use OnixReader for files.
 */
final class ProductParser
{
    private const ONIX3_BLOCKS = ['DescriptiveDetail', 'CollateralDetail', 'PromotionDetail', 'ContentDetail', 'PublishingDetail', 'RelatedMaterial', 'ProductionDetail', 'ProductSupply'];

    private const ONIX21_ELEMENTS = ['Title', 'DistinctiveTitle', 'Contributor', 'OtherText', 'MediaFile', 'SupplyDetail', 'ProductForm', 'Subject', 'Imprint', 'Publisher', 'PublicationDate', 'Series', 'NumberOfPages', 'ISBN', 'EAN13', 'BASICMainSubject', 'BISACMainSubject', 'LanguageOfText', 'Language', 'Extent'];

    private readonly ReaderOptions $options;

    public function __construct(?ReaderOptions $options = null)
    {
        $this->options = $options ?? new ReaderOptions();
    }

    /**
     * @param OnixVersion|null $version the message's version, if known; the product's own structure wins when it is unambiguous
     *
     * @throws InvalidProductException when the XML is not well-formed, holds no product, or
     *                                  the product has neither a RecordReference nor an identifier
     */
    public function parse(string $xml, ?OnixVersion $version = null, ?int $index = null): Product
    {
        $root = $this->load($xml, $index);
        $product = 'Product' === $root->name() ? $root : $root->first('Product');
        if (null === $product) {
            throw new InvalidProductException('No Product element found', new ProductError($index, null, 'No Product element found', $xml));
        }
        if (null === $product->text('RecordReference') && [] === $product->children('ProductIdentifier', 'ISBN', 'EAN13')) {
            $message = 'Product has no RecordReference and no ProductIdentifier';

            throw new InvalidProductException($message, new ProductError($index, null, $message, $xml));
        }
        $version = $this->options->version ?? self::detectVersion($product, $version ?? OnixVersion::fromRelease($root->attribute('release')));

        try {
            return $version->isOnix3()
                ? (new Onix30Mapper($this->options->codeLists))->map($product, $version, $index)
                : (new Onix21Mapper($this->options->codeLists))->map($product, $index);
        } catch (Throwable $e) {
            $message = sprintf('Could not map product: %s', $e->getMessage());

            throw new InvalidProductException($message, new ProductError($index, $product->text('RecordReference'), $message, $xml), $e);
        }
    }

    /**
     * Parses a Header element, or the header of a whole message. Null when there is none.
     */
    public function parseHeader(string $xml, ?OnixVersion $version = null): ?Header
    {
        $root = $this->load($xml, null);
        $header = 'Header' === $root->name() ? $root : $root->first('Header');
        if (null === $header) {
            return null;
        }

        return (new HeaderMapper($this->options->codeLists))->map($header, $this->options->version ?? $version ?? OnixVersion::fromRelease($root->attribute('release')));
    }

    /**
     * ONIX 3.0 if the product has any of the 3.0 blocks; 2.1 if it has 2.1-only
     * top-level elements; otherwise the message's version, else 3.0.
     */
    public static function detectVersion(Element $product, ?OnixVersion $messageVersion = null): OnixVersion
    {
        foreach ($product->children() as $child) {
            if (\in_array($child->name(), self::ONIX3_BLOCKS, true)) {
                return OnixVersion::V31 === $messageVersion ? OnixVersion::V31 : OnixVersion::V30;
            }
        }
        foreach ($product->children() as $child) {
            if (\in_array($child->name(), self::ONIX21_ELEMENTS, true)) {
                return OnixVersion::V21;
            }
        }

        return $messageVersion ?? OnixVersion::V30;
    }

    private function load(string $xml, ?int $index): Element
    {
        if ($this->options->fixEntities) {
            $xml = EntityFixer::fix($xml);
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $loaded = '' !== trim($xml) && $document->loadXML($xml, \LIBXML_NONET | \LIBXML_COMPACT);
            $error = libxml_get_last_error();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->documentElement;
        if (!$loaded || !$root instanceof DOMElement) {
            $message = false !== $error
                ? sprintf('Malformed XML at line %d: %s', $error->line, trim($error->message))
                : 'Empty or malformed XML';
            $reference = 1 === preg_match('#<(?:RecordReference|a001)>\s*([^<]+?)\s*</#', $xml, $m) ? $m[1] : null;

            throw new InvalidProductException($message, new ProductError($index, $reference, $message, $xml));
        }

        return Element::fromDom($root);
    }
}
