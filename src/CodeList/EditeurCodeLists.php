<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

use DOMDocument;
use DOMElement;
use ISBNLab\Onix\Exception\OnixException;

/**
 * Reads the code lists exactly as EDItEUR publishes them, in the JSON or XML format
 * from https://www.editeur.org/14/Code-Lists/ (for example
 * ONIX_BookProduct_Codelists_Issue_75.json).
 *
 * The code lists are EDItEUR's copyright and are not shipped with this package; you
 * download them yourself under EDItEUR's licence (https://doi.org/10.4400/nwgj).
 */
final class EditeurCodeLists
{
    public static function fromFile(string $path): ArrayCodeLists
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new OnixException(sprintf('Code list file not readable: %s', $path));
        }
        $contents = (string) file_get_contents($path);
        $trimmed = ltrim($contents, "\xEF\xBB\xBF \t\r\n");

        return str_starts_with($trimmed, '{') ? self::fromJson($trimmed) : self::fromXml($trimmed);
    }

    public static function fromJson(string $json): ArrayCodeLists
    {
        $data = json_decode($json, true);
        if (!\is_array($data) || !isset($data['ONIXCodeTable']) || !\is_array($data['ONIXCodeTable'])) {
            throw new OnixException('Not an EDItEUR ONIX code list JSON file (no ONIXCodeTable).');
        }
        $table = $data['ONIXCodeTable'];
        $lists = [];
        $codeLists = $table['CodeList'] ?? [];
        foreach (\is_array($codeLists) ? $codeLists : [] as $codeList) {
            if (!\is_array($codeList) || !is_numeric($codeList['CodeListNumber'] ?? null)) {
                continue;
            }
            $number = (int) $codeList['CodeListNumber'];
            $codes = $codeList['Code'] ?? [];
            foreach (\is_array($codes) ? $codes : [] as $code) {
                if (!\is_array($code)) {
                    continue;
                }
                $value = $code['CodeValue'] ?? null;
                $label = $code['CodeDescription'] ?? null;
                if ((\is_string($value) || \is_int($value)) && \is_string($label)) {
                    $lists[$number][(string) $value] = $label;
                }
            }
        }
        $issue = $table['IssueNumber'] ?? null;

        return new ArrayCodeLists($lists, is_numeric($issue) ? (int) $issue : null);
    }

    public static function fromXml(string $xml): ArrayCodeLists
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, \LIBXML_NONET | \LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->documentElement;
        if (!$loaded || null === $root || 'ONIXCodeTable' !== $root->localName) {
            throw new OnixException('Not an EDItEUR ONIX code list XML file (no ONIXCodeTable).');
        }

        $lists = [];
        $issue = null;
        foreach ($root->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ('IssueNumber' === $child->localName) {
                $issue = (int) trim($child->textContent);
                continue;
            }
            if ('CodeList' !== $child->localName) {
                continue;
            }
            $number = null;
            foreach ($child->childNodes as $listChild) {
                if (!$listChild instanceof DOMElement) {
                    continue;
                }
                if ('CodeListNumber' === $listChild->localName) {
                    $number = (int) trim($listChild->textContent);
                } elseif ('Code' === $listChild->localName && null !== $number) {
                    $value = null;
                    $label = null;
                    foreach ($listChild->childNodes as $field) {
                        if ($field instanceof DOMElement && 'CodeValue' === $field->localName) {
                            $value = trim($field->textContent);
                        } elseif ($field instanceof DOMElement && 'CodeDescription' === $field->localName) {
                            $label = trim($field->textContent);
                        }
                    }
                    if (null !== $value && null !== $label) {
                        $lists[$number][$value] = $label;
                    }
                }
            }
        }

        return new ArrayCodeLists($lists, $issue);
    }
}
