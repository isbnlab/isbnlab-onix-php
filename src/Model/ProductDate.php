<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Model;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A dated event: publishing date, supply date or similar.
 */
final class ProductDate
{
    public function __construct(
        /** For publishing dates, list 163: 01 publication date, 11 first publication... */
        public readonly ?Code $role,
        /** The date exactly as given, for example "20240315", "202403" or "2024". */
        public readonly string $value,
        /** List 55, when the feed says which format the date uses. */
        public readonly ?Code $format = null,
    ) {}

    /**
     * Best-effort conversion. Partial dates resolve to their first day ("2024" is
     * 1 January 2024); unparseable values give null.
     */
    public function toDateTime(): ?DateTimeImmutable
    {
        $value = preg_replace('/[^0-9]/', '', $this->value) ?? '';
        $format = match (\strlen($value)) {
            8, 12, 14 => 'Ymd',
            6 => 'Ym',
            4 => 'Y',
            default => null,
        };
        if (null === $format || (null !== $this->format && !$this->format->is('00', '01', '05', '13', '14'))) {
            return null;
        }
        $value = substr($value, 0, 8);
        $year = (int) substr($value, 0, 4);
        $month = 4 === \strlen($value) ? 1 : (int) substr($value, 4, 2);
        $day = 8 === \strlen($value) ? (int) substr($value, 6, 2) : 1;
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setDate($year, $month, $day)->setTime(0, 0);
    }
}
