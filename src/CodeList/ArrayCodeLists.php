<?php

declare(strict_types=1);

namespace ISBNLab\Onix\CodeList;

use ISBNLab\Onix\Exception\OnixException;

/**
 * Code lists held in memory as list number => code => label.
 */
final class ArrayCodeLists implements CodeLists
{
    /**
     * @param array<int, array<string, string>> $lists
     */
    public function __construct(
        private readonly array $lists,
        private readonly ?int $issue = null,
    ) {}

    /**
     * Loads a file written by `vendor/bin/onix-codelists`.
     */
    public static function fromPhpFile(string $path): self
    {
        if (!is_file($path)) {
            throw new OnixException(sprintf('Code list file not found: %s', $path));
        }
        $data = require $path;
        if (!\is_array($data) || !isset($data['lists']) || !\is_array($data['lists'])) {
            throw new OnixException(sprintf('Not a compiled code list file: %s', $path));
        }
        $lists = [];
        foreach ($data['lists'] as $number => $codes) {
            if (!\is_array($codes)) {
                continue;
            }
            foreach ($codes as $value => $label) {
                if (\is_string($label)) {
                    $lists[(int) $number][(string) $value] = $label;
                }
            }
        }
        $issue = $data['issue'] ?? null;

        return new self($lists, \is_int($issue) ? $issue : null);
    }

    public function label(int $list, string $value): ?string
    {
        return $this->lists[$list][$value] ?? null;
    }

    public function issue(): ?int
    {
        return $this->issue;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return $this->lists;
    }
}
