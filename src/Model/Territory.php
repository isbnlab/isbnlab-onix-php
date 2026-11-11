<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

final class Territory
{
    /**
     * @param list<string> $countriesIncluded ISO 3166-1 alpha-2
     * @param list<string> $regionsIncluded list 49, or "WORLD"
     * @param list<string> $countriesExcluded
     * @param list<string> $regionsExcluded
     */
    public function __construct(
        public readonly array $countriesIncluded = [],
        public readonly array $regionsIncluded = [],
        public readonly array $countriesExcluded = [],
        public readonly array $regionsExcluded = [],
    ) {}

    public function isEmpty(): bool
    {
        return [] === $this->countriesIncluded && [] === $this->regionsIncluded
            && [] === $this->countriesExcluded && [] === $this->regionsExcluded;
    }

    /**
     * Whether the territory covers a country, from what the feed states.
     */
    public function includesCountry(string $country): bool
    {
        if (\in_array($country, $this->countriesExcluded, true)) {
            return false;
        }

        return \in_array($country, $this->countriesIncluded, true) || \in_array('WORLD', $this->regionsIncluded, true);
    }
}
