<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

use ISBNLab\Onix\CodeList\CodeLists;
use ISBNLab\Onix\Model\Code;
use ISBNLab\Onix\Model\Contributor;
use ISBNLab\Onix\Model\Identifier;
use ISBNLab\Onix\Model\Measure;
use ISBNLab\Onix\Model\Subject;
use ISBNLab\Onix\Model\TextContent;
use ISBNLab\Onix\Model\TitleElement;

/**
 * Helpers shared by the ONIX 2.1 and 3.0 mappers. One instance maps one product.
 *
 * @internal
 */
abstract class Mapper
{
    /** @var list<string> */
    protected array $warnings = [];

    public function __construct(protected readonly CodeLists $codeLists) {}

    /** Lists whose codes are legitimately one digit long. */
    private const SINGLE_DIGIT_LISTS = [76, 77, 227, 238];

    protected function code(int $list, ?string $value, ?int $labelList = null): ?Code
    {
        if (null === $value) {
            return null;
        }
        if (1 === preg_match('/^\d$/', $value) && !\in_array($list, self::SINGLE_DIGIT_LISTS, true)) {
            // Some feeds drop the leading zero of two-digit codes ("1" for "01").
            $value = '0' . $value;
        }

        return new Code($list, $value, $this->codeLists->label($labelList ?? $list, $value));
    }

    /**
     * @return list<Code>
     */
    protected function codes(int $list, Element $parent, string $childName, ?int $labelList = null): array
    {
        $codes = [];
        foreach ($parent->texts($childName) as $value) {
            $code = $this->code($list, $value, $labelList);
            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    protected function number(?string $raw, string $what): ?float
    {
        if (null === $raw) {
            return null;
        }
        $value = str_replace([' ', "\u{00A0}"], '', $raw);
        if (1 === preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
            // Thousands separators: "1,024", "1,299.00".
            $value = str_replace(',', '', $value);
        } elseif (1 === preg_match('/^-?\d+,\d+$/', $value)) {
            // Decimal comma: "12,50".
            $value = str_replace(',', '.', $value);
        }
        if (!is_numeric($value)) {
            $this->warnings[] = sprintf('%s "%s" is not a number', $what, $raw);

            return null;
        }

        return (float) $value;
    }

    protected function integer(?string $raw, string $what): ?int
    {
        $number = $this->number($raw, $what);
        if (null === $number) {
            return null;
        }
        if ((float) (int) $number !== $number) {
            $this->warnings[] = sprintf('%s "%s" is not a whole number', $what, (string) $raw);
        }

        return (int) $number;
    }

    /**
     * Identifier composites with an IDType, IDTypeName and IDValue child.
     *
     * @return list<Identifier>
     */
    protected function identifiers(Element $parent, string $composite, string $typeElement, int $list): array
    {
        $identifiers = [];
        foreach ($parent->children($composite) as $element) {
            $value = $element->text('IDValue');
            if (null === $value) {
                continue;
            }
            $identifiers[] = new Identifier($this->code($list, $element->text($typeElement)), $value, $element->text('IDTypeName'));
        }

        return $identifiers;
    }

    protected function titleElement(Element $element, ?Code $level): TitleElement
    {
        return new TitleElement(
            level: $level,
            text: $element->text('TitleText'),
            prefix: $element->text('TitlePrefix'),
            withoutPrefix: $element->text('TitleWithoutPrefix'),
            subtitle: $element->text('Subtitle'),
            partNumber: $element->text('PartNumber'),
            yearOfAnnual: $element->text('YearOfAnnual'),
            sequenceNumber: $this->integer($element->text('SequenceNumber'), 'TitleElement SequenceNumber'),
        );
    }

    /**
     * @param list<Identifier> $identifiers
     */
    protected function contributor(Element $element, array $identifiers): Contributor
    {
        return new Contributor(
            roles: $this->codes(17, $element, 'ContributorRole'),
            sequenceNumber: $this->integer($element->text('SequenceNumber'), 'Contributor SequenceNumber'),
            identifiers: $identifiers,
            personName: $element->text('PersonName'),
            personNameInverted: $element->text('PersonNameInverted'),
            titlesBeforeNames: $element->text('TitlesBeforeNames'),
            namesBeforeKey: $element->text('NamesBeforeKey'),
            prefixToKey: $element->text('PrefixToKey'),
            keyNames: $element->text('KeyNames'),
            namesAfterKey: $element->text('NamesAfterKey'),
            suffixToKey: $element->text('SuffixToKey'),
            corporateName: $element->text('CorporateName'),
            corporateNameInverted: $element->text('CorporateNameInverted'),
            biographicalNotes: array_map(fn(Element $note): string => $this->markup($note), $element->children('BiographicalNote')),
            unnamedPersons: $this->code(19, $element->text('UnnamedPersons')),
        );
    }

    /**
     * Sorts by SequenceNumber when every item has one; otherwise keeps file order.
     *
     * @template T of Contributor|TitleElement
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    protected function sortBySequence(array $items): array
    {
        foreach ($items as $item) {
            if (null === $item->sequenceNumber) {
                return $items;
            }
        }
        usort($items, static fn(Contributor|TitleElement $a, Contributor|TitleElement $b): int => $a->sequenceNumber <=> $b->sequenceNumber);

        return $items;
    }

    protected function subject(Element $element, bool $main, int $schemeList = 27): Subject
    {
        return new Subject(
            scheme: $this->code($schemeList, $element->text('SubjectSchemeIdentifier') ?? $element->text('MainSubjectSchemeIdentifier'), 27 === $schemeList ? null : 27),
            code: $element->text('SubjectCode'),
            headingText: $element->text('SubjectHeadingText'),
            main: $main,
            schemeName: $element->text('SubjectSchemeName'),
            schemeVersion: $element->text('SubjectSchemeVersion'),
        );
    }

    /**
     * @return list<Measure>
     */
    protected function measures(Element $parent, string $typeElement): array
    {
        $measures = [];
        foreach ($parent->children('Measure') as $element) {
            $raw = $element->text('Measurement');
            $measures[] = new Measure(
                type: $this->code(48, $element->text($typeElement)),
                value: $this->number($raw, 'Measurement'),
                unit: $this->code(50, $element->text('MeasureUnitCode')),
                rawValue: $raw,
            );
        }

        return $measures;
    }

    /**
     * @param list<Code> $audiences
     */
    protected function text(Element $element, ?Code $type, ?string $format, array $audiences = []): ?TextContent
    {
        $text = $this->markup($element);
        if ('' === $text) {
            return null;
        }

        return new TextContent($type, $text, $format ?? $element->attribute('textformat'), $element->attribute('language'), $audiences);
    }

    /**
     * Text content, or the inner XML when the element holds XHTML markup.
     */
    protected function markup(Element $element): string
    {
        if (!$element->hasElementChildren()) {
            return $element->text() ?? '';
        }
        $xml = $element->innerXml();
        // Drop namespace declarations and prefixes from embedded XHTML.
        $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace('#<(/?)\w+:#', '<$1', $xml) ?? $xml;

        return trim($xml);
    }

    /**
     * @return list<string>
     */
    protected function splitList(?string $value): array
    {
        if (null === $value) {
            return [];
        }

        $parts = preg_split('/\s+/', $value);

        return false === $parts ? [] : array_values(array_filter($parts, static fn(string $v): bool => '' !== $v));
    }
}
