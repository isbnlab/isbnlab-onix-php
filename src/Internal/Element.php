<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

use DOMElement;
use DOMNode;

/**
 * Read-only view of a DOM element that answers to ONIX reference names whether the
 * document uses reference names or short tags.
 *
 * @internal
 */
final class Element
{
    /** @var list<Element>|null */
    private ?array $children = null;

    private function __construct(
        private readonly DOMElement $node,
        private readonly string $name,
    ) {}

    public static function fromDom(DOMElement $node): self
    {
        return new self($node, self::referenceName($node->localName ?? $node->nodeName));
    }

    public static function referenceName(string $localName): string
    {
        return TagMap::toReference($localName) ?? $localName;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function localName(): string
    {
        return $this->node->localName ?? $this->node->nodeName;
    }

    /**
     * Child elements, all of them or only those with the given reference name(s).
     *
     * @return list<Element>
     */
    public function children(string ...$names): array
    {
        if (null === $this->children) {
            $this->children = [];
            foreach ($this->node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $this->children[] = self::fromDom($child);
                }
            }
        }
        if ([] === $names) {
            return $this->children;
        }

        $matches = [];
        foreach ($this->children as $child) {
            if (\in_array($child->name, $names, true)) {
                $matches[] = $child;
            }
        }

        return $matches;
    }

    public function first(string ...$names): ?self
    {
        foreach ($this->children(...$names) as $child) {
            return $child;
        }

        return null;
    }

    public function has(string $name): bool
    {
        return null !== $this->first($name);
    }

    /**
     * Trimmed text of this element, or of its first child with the given name.
     * Empty text is returned as null.
     */
    public function text(?string $childName = null): ?string
    {
        $element = null === $childName ? $this : $this->first($childName);
        if (null === $element) {
            return null;
        }
        $text = trim($element->node->textContent);

        return '' === $text ? null : $text;
    }

    /**
     * Trimmed texts of every child with the given name, empty ones dropped.
     *
     * @return list<string>
     */
    public function texts(string $childName): array
    {
        $texts = [];
        foreach ($this->children($childName) as $child) {
            $text = $child->text();
            if (null !== $text) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    public function attribute(string $name): ?string
    {
        if (!$this->node->hasAttribute($name)) {
            return null;
        }
        $value = trim($this->node->getAttribute($name));

        return '' === $value ? null : $value;
    }

    public function hasElementChildren(): bool
    {
        return [] !== $this->children();
    }

    /**
     * The element's content as XML, without the element's own tags.
     */
    public function innerXml(): string
    {
        $document = $this->node->ownerDocument;
        if (null === $document) {
            return $this->node->textContent;
        }
        $xml = '';
        /** @var DOMNode $child */
        foreach ($this->node->childNodes as $child) {
            $xml .= (string) $document->saveXML($child);
        }

        return $xml;
    }

    public function outerXml(): string
    {
        $document = $this->node->ownerDocument;

        return null === $document ? '' : (string) $document->saveXML($this->node);
    }
}
