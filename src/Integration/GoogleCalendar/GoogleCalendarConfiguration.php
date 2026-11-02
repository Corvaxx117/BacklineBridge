<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar;

use App\Entity\Band;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GoogleCalendarConfiguration
{
    public function __construct(
        #[Autowire('%env(GOOGLE_CALENDAR_BAND_ID)%')] private string $bandId,
        #[Autowire('%env(GOOGLE_CALENDAR_ID)%')] private string $calendarId,
        #[Autowire('%env(resolve:GOOGLE_CALENDAR_CREDENTIALS_FILE)%')] private string $credentialsFile,
        #[Autowire('%env(GOOGLE_CALENDAR_NAMESPACE)%')] private string $namespace,
        #[Autowire('%env(GOOGLE_CALENDAR_TIMEZONE)%')] private string $timezone,
        #[Autowire('%env(GOOGLE_CALENDAR_NAME)%')] private string $name = 'Avee Mana Partagé',
    ) {
    }

    public function isConfiguredFor(Band $band): bool
    {
        return null !== $band->getId()
            && (string) $band->getId() === $this->bandId
            && '' !== trim($this->calendarId)
            && 'primary' !== $this->calendarId
            && strlen($this->calendarId) <= 1024
            && '' !== $this->credentialsFile
            && 1 === preg_match('/^[a-f0-9]{32}$/D', $this->namespace)
            && in_array($this->timezone, \DateTimeZone::listIdentifiers(), true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function calendarId(): string
    {
        return $this->calendarId;
    }

    public function credentialsFile(): string
    {
        return $this->credentialsFile;
    }

    public function timezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone);
    }

    public function eventId(int $concertId): string
    {
        // Identifiant stable par installation : les doubles clics et les reprises restent sans doublon.
        return 'bb'.hash('sha256', $this->namespace.':'.$this->bandId.':'.$concertId);
    }

    public function cacheKey(): string
    {
        return 'google_calendar.'.hash('sha256', $this->namespace.':'.$this->bandId.':'.$this->calendarId.':'.$this->credentialsFile.':'.$this->timezone);
    }
}
