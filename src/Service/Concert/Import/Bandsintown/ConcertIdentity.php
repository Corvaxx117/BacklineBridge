<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Bandsintown;

final class ConcertIdentity
{
    public static function key(string $date, string $venue, string $city): string
    {
        $normalize = static fn (string $value): string => preg_replace(
            '/[^\pL\pN]+/u',
            '',
            mb_strtolower($value),
        ) ?? '';

        return hash('sha256', $date.'|'.$normalize($venue).'|'.$normalize($city));
    }
}
