<?php

declare(strict_types=1);

namespace App\Dto\Calendar;

final readonly class CalendarPeriod
{
    public function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $until)
    {
        if ($until <= $from || $until->getTimestamp() - $from->getTimestamp() > 94 * 86400) {
            throw new \InvalidArgumentException('La période de l’agenda est invalide.');
        }
    }

    public static function fromQuery(string $start, string $end, \DateTimeZone $timezone): self
    {
        $from = self::day($start, $timezone);
        $until = self::day($end, $timezone);
        if ($until <= $from || (int) $from->diff($until)->days > 62) {
            throw new \InvalidArgumentException('La période demandée doit couvrir entre 1 et 62 jours.');
        }

        return new self($from, $until);
    }

    private static function day(string $value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        if (1 !== preg_match('/^(?:19[7-9][0-9]|20[0-9]{2}|210[01])-\d{2}-\d{2}$/D', $value)) {
            throw new \InvalidArgumentException('Indique une date valide pour l’agenda.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Indique une date valide pour l’agenda.');
        }

        return $date;
    }
}
