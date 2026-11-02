<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar\Dto;

final readonly class AgendaEvent
{
    public function __construct(
        public string $title,
        public string $location,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $allDay,
        public ?string $url,
    ) {
    }

    public static function safeUrl(mixed $url): ?string
    {
        if (!is_string($url) || strlen($url) > 4096 || false === filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? '', ['calendar.google.com', 'www.google.com'], true)
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['port'])
                ? $url
                : null;
    }
}
