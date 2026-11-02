<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar;

use App\Integration\GoogleCalendar\Dto\AgendaEvent;

final readonly class GoogleEventNormalizer
{
    public function __construct(private GoogleCalendarConfiguration $configuration)
    {
    }

    /** @param array<string, mixed> $data */
    public function normalize(array $data): AgendaEvent
    {
        $start = $data['start'] ?? null;
        $end = $data['end'] ?? null;
        if (!is_array($start) || !is_array($end)) {
            throw new \UnexpectedValueException('Dates Google invalides.');
        }
        $allDay = isset($start['date']);
        $startsAt = $this->date($start, $allDay);
        $endsAt = $this->date($end, $allDay);
        if ($endsAt <= $startsAt) {
            throw new \UnexpectedValueException('Période Google invalide.');
        }

        return new AgendaEvent(
            is_string($data['summary'] ?? null) && '' !== trim($data['summary'])
                ? mb_substr($data['summary'], 0, 500)
                : 'Événement sans titre',
            is_string($data['location'] ?? null) ? mb_substr($data['location'], 0, 1000) : '',
            $startsAt,
            $allDay ? $endsAt->modify('-1 day') : $endsAt,
            $allDay,
            AgendaEvent::safeUrl($data['htmlLink'] ?? null),
        );
    }

    /** @param array<string, mixed> $data */
    private function date(array $data, bool $allDay): \DateTimeImmutable
    {
        $value = $data[$allDay ? 'date' : 'dateTime'] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Date Google manquante.');
        }
        if ($allDay) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->configuration->timezone());
            if (false === $date || $date->format('Y-m-d') !== $value) {
                throw new \UnexpectedValueException('Jour Google invalide.');
            }

            return $date;
        }
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new \UnexpectedValueException('Horaire Google invalide.');
        }
        $date = new \DateTimeImmutable($value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new \UnexpectedValueException('Horaire Google invalide.');
        }

        return $date->setTimezone($this->configuration->timezone());
    }
}
