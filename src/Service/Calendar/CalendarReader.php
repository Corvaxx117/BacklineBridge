<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Dto\Calendar\CalendarPeriod;
use App\Entity\Band;
use App\Integration\GoogleCalendar\Dto\AgendaListing;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Integration\GoogleCalendar\GoogleCalendarGateway;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class CalendarReader
{
    public function __construct(
        private GoogleCalendarGateway $google,
        private GoogleCalendarConfiguration $configuration,
        #[Autowire(service: 'cache.google_calendar')] private TagAwareCacheInterface $cache,
    ) {
    }

    public function upcoming(Band $band): AgendaListing
    {
        $this->assertConfigured($band);
        $from = new \DateTimeImmutable('today', $this->configuration->timezone());

        return $this->forPeriod($band, new CalendarPeriod($from, $from->modify('+90 days')));
    }

    public function forPeriod(Band $band, CalendarPeriod $period): AgendaListing
    {
        $this->assertConfigured($band);
        $tag = $this->configuration->cacheKey();
        $key = $tag.'.'.$period->from->format('Ymd').'.'.$period->until->format('Ymd');

        return $this->cache->get($key, function (ItemInterface $item) use ($period, $tag): AgendaListing {
            $item->expiresAfter(300);
            $item->tag($tag);

            return $this->google->events($period->from, $period->until);
        });
    }

    public function invalidate(): void
    {
        $this->cache->invalidateTags([$this->configuration->cacheKey()]);
    }

    private function assertConfigured(Band $band): void
    {
        if (!$this->configuration->isConfiguredFor($band)) {
            throw new GoogleCalendarUnavailable('L’agenda du groupe n’est pas encore connecté.');
        }
    }
}
