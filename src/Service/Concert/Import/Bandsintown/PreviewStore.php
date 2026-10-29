<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Bandsintown;

use App\Entity\{Band, User};
use App\Integration\Bandsintown\Dto\{BandsintownEvent, EventsResult};
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PreviewStore
{
    public function __construct(private RequestStack $requests, private ClockInterface $clock) {}

    /** @param list<BandsintownEvent> $events */
    public function save(Band $band, User $user, EventsResult $result, array $events): string
    {
        $token = bin2hex(random_bytes(16));
        $session = $this->requests->getSession();
        $previews = $session->get('bandsintown_previews', []);
        $previews = array_filter(
            $previews,
            fn(array $p): bool => $p['expires'] > $this->clock->now()->getTimestamp(),
        );
        $previews[$token] = [
            'band' => $band->getId(),
            'user' => $user->getId(),
            'expires' => $this->clock->now()->getTimestamp() + 900,
            'events' => array_map(
                static fn(BandsintownEvent $event): array => $event->toArray(),
                $events,
            ),
            'ignored' => $result->ignored,
            'fetchedAt' => $result->fetchedAt->format(DATE_ATOM),
        ];
        $session->set('bandsintown_previews', array_slice($previews, -5, null, true));
        return $token;
    }

    public function load(string $token, Band $band, User $user): EventsResult
    {
        $preview = $this->requests->getSession()->get('bandsintown_previews', [])[$token] ?? null;
        if (
            !is_array($preview) ||
            $preview['band'] !== $band->getId() ||
            $preview['user'] !== $user->getId() ||
            $preview['expires'] <= $this->clock->now()->getTimestamp()
        ) {
            throw new NotFoundHttpException(
                'Cet aperçu est expiré ou indisponible. Recharge les concerts Bandsintown.',
            );
        }
        return new EventsResult(
            array_map(BandsintownEvent::fromArray(...), $preview['events']),
            $preview['ignored'],
            new \DateTimeImmutable($preview['fetchedAt']),
        );
    }

    public function remove(string $token): void
    {
        $session = $this->requests->getSession();
        $previews = $session->get('bandsintown_previews', []);
        unset($previews[$token]);
        $session->set('bandsintown_previews', $previews);
    }
}
