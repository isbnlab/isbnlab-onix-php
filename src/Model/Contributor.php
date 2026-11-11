<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Contributor
{
    /**
     * @param list<Code> $roles list 17
     * @param list<Identifier> $identifiers
     * @param list<string> $biographicalNotes
     */
    public function __construct(
        public readonly array $roles,
        public readonly ?int $sequenceNumber = null,
        public readonly array $identifiers = [],
        public readonly ?string $personName = null,
        public readonly ?string $personNameInverted = null,
        public readonly ?string $titlesBeforeNames = null,
        public readonly ?string $namesBeforeKey = null,
        public readonly ?string $prefixToKey = null,
        public readonly ?string $keyNames = null,
        public readonly ?string $namesAfterKey = null,
        public readonly ?string $suffixToKey = null,
        public readonly ?string $corporateName = null,
        public readonly ?string $corporateNameInverted = null,
        public readonly array $biographicalNotes = [],
        public readonly ?Code $unnamedPersons = null,
    ) {}

    public function hasRole(string ...$roles): bool
    {
        foreach ($this->roles as $role) {
            if ($role->is(...$roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A name in natural order: PersonName, else the name parts, else PersonNameInverted
     * turned around ("Austen, Jane" becomes "Jane Austen"), else the corporate name.
     */
    public function displayName(): ?string
    {
        if (null !== $this->personName) {
            return $this->personName;
        }
        if (null !== $this->keyNames) {
            $parts = array_filter(
                [$this->titlesBeforeNames, $this->namesBeforeKey, $this->prefixToKey, $this->keyNames, $this->namesAfterKey],
                static fn(?string $part): bool => null !== $part,
            );
            $name = implode(' ', $parts);

            return null === $this->suffixToKey ? $name : $name . ', ' . $this->suffixToKey;
        }
        if (null !== $this->personNameInverted) {
            $parts = array_map('trim', explode(',', $this->personNameInverted, 2));
            if (2 === \count($parts) && '' !== $parts[1]) {
                return $parts[1] . ' ' . $parts[0];
            }

            return $this->personNameInverted;
        }

        return $this->corporateName ?? $this->corporateNameInverted;
    }
}
