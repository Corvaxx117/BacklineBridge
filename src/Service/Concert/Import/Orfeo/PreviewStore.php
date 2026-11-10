<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Orfeo;

use App\Entity\Band;
use App\Entity\User;
use App\Integration\Orfeo\Dto\EventsResult;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Integration\Orfeo\OrfeoConfiguration;
use App\Repository\OrfeoConcertRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PreviewStore
{
    public function __construct(
        private RequestStack $requests,
        private ClockInterface $clock,
        private OrfeoConfiguration $configuration,
        private ImportPreview $preview,
        private OrfeoConcertRepository $sources,
    ) {
    }

    public function save(Band $band, User $user, EventsResult $result): string
    {
        $token = bin2hex(random_bytes(16));
        $session = $this->requests->getSession();
        $previews = $session->get('orfeo_previews', []);
        $previews = array_filter($previews, fn (array $p): bool => $p['expires'] > $this->clock->now()->getTimestamp());
        $previews[$token] = ['band' => $band->getId(), 'user' => $user->getId(),
            'scope' => $this->configuration->cacheKey(), 'expires' => $this->clock->now()->getTimestamp() + 900,
            'events' => array_map(static fn (OrfeoEvent $event): array => $event->toArray(), $result->events),
            'ignored' => $result->ignored, 'fetchedAt' => $result->fetchedAt->format(DATE_ATOM),
            'sourceVersions' => array_column($this->sources->identitiesForBand($band), 'revision', 'id'),
            'versions' => array_map(static fn (array $row): int => $row['revision'], $this->preview->concerts($band))];
        $session->set('orfeo_previews', array_slice($previews, -5, null, true));

        return $token;
    }

    /** @return array{result: EventsResult, versions: array<int, int>, sourceVersions: array<int, int>} */
    public function load(string $token, Band $band, User $user): array
    {
        $data = $this->requests->getSession()->get('orfeo_previews', [])[$token] ?? null;
        if (!is_array($data) || $data['band'] !== $band->getId() || $data['user'] !== $user->getId()
            || $data['scope'] !== $this->configuration->cacheKey() || $data['expires'] <= $this->clock->now()->getTimestamp()) {
            throw new NotFoundHttpException('Cet aperçu Orfeo a expiré. Recharge les dates.');
        }

        return ['result' => new EventsResult(array_values(array_map(OrfeoEvent::fromArray(...), $data['events'])),
            $data['ignored'], new \DateTimeImmutable($data['fetchedAt'])), 'versions' => $data['versions'], 'sourceVersions' => $data['sourceVersions']];
    }

    public function remove(string $token): void
    {
        $session = $this->requests->getSession();
        $previews = $session->get('orfeo_previews', []);
        unset($previews[$token]);
        $session->set('orfeo_previews', $previews);
    }
}
