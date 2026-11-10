<?php

declare(strict_types=1);

namespace App\Integration\Orfeo;

use App\Integration\Orfeo\Dto\EventsResult;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Integration\Orfeo\Exception\OrfeoUnavailable;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class OrfeoClient implements OrfeoGateway
{
    public function __construct(
        private OrfeoApi $api,
        private OrfeoConfiguration $configuration,
        private OrfeoEventNormalizer $normalizer,
        #[Autowire(service: 'cache.orfeo')] private CacheInterface $cache,
        private ClockInterface $clock,
    ) {
    }

    public function events(): EventsResult
    {
        if ([] === $this->configuration->spectacles()) {
            throw new OrfeoUnavailable('Indique les identifiants des spectacles Avee Mana dans la configuration privée.');
        }
        $data = $this->cache->get('events.v2.'.$this->configuration->cacheKey(), function (ItemInterface $item): array {
            $item->expiresAfter(300);
            $events = [];
            $ignored = 0;
            $seenProjects = [];
            foreach ($this->configuration->spectacles() as $spectacle) {
                foreach ($this->api->list('project', ['spectacle' => $spectacle]) as $project) {
                    if ((int) ($project['spectacle']['pk'] ?? 0) !== $spectacle) {
                        throw new OrfeoUnavailable('Orfeo a renvoyé un projet hors du périmètre autorisé. Import interrompu.');
                    }
                    $id = (int) ($project['pk'] ?? 0);
                    if ($id < 1 || isset($seenProjects[$id])) {
                        ++$ignored;
                        continue;
                    }
                    $seenProjects[$id] = true;
                    if (count($seenProjects) > 500) {
                        throw new OrfeoUnavailable('Trop de projets. Réduis le périmètre configuré.');
                    }
                    $contacts = $this->api->list('project_linked_contact', ['parent' => $id]);
                    try {
                        $normalized = $this->normalizer->normalize($project, $contacts);
                    } catch (\UnexpectedValueException) {
                        ++$ignored;
                        continue;
                    }
                    if ([] === $normalized) {
                        ++$ignored;
                    }
                    foreach ($normalized as $event) {
                        if (isset($events[$event->id])) {
                            ++$ignored;
                        } else {
                            $events[$event->id] = $event->toArray();
                        }
                    }
                }
            }

            return ['events' => array_values($events), 'ignored' => $ignored,
                'fetchedAt' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)];
        });

        return new EventsResult(array_map(OrfeoEvent::fromArray(...), $data['events']),
            $data['ignored'], new \DateTimeImmutable($data['fetchedAt']));
    }
}
