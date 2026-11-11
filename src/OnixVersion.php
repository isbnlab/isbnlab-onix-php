<?php

declare(strict_types=1);

namespace ISBNLab\Onix;

enum OnixVersion: string
{
    case V21 = '2.1';
    case V30 = '3.0';
    case V31 = '3.1';

    public static function fromRelease(?string $release): ?self
    {
        $release = trim((string) $release);

        return match (true) {
            str_starts_with($release, '3.1') => self::V31,
            str_starts_with($release, '3') => self::V30,
            str_starts_with($release, '2') => self::V21,
            default => null,
        };
    }

    public function isOnix3(): bool
    {
        return self::V21 !== $this;
    }
}
