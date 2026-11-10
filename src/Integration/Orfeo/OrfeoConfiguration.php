<?php

declare(strict_types=1);

namespace App\Integration\Orfeo;

use App\Entity\Band;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrfeoConfiguration
{
    public function __construct(
        #[Autowire('%env(ORFEO_BAND_ID)%')] private string $bandId,
        #[Autowire('%env(ORFEO_API_KEY)%')] #[\SensitiveParameter] private string $apiKey,
        #[Autowire('%env(ORFEO_SPECTACLE_IDS)%')] private string $spectacleIds,
        #[Autowire('%env(ORFEO_CONFIRMED_STATUS_IDS)%')] private string $confirmedStatusIds,
        #[Autowire('%env(ORFEO_TIMEZONE)%')] public string $timezone,
        #[Autowire('%env(bool:APP_DEMO_MODE)%')] private bool $demoMode,
    ) {
    }

    public function isConfiguredFor(Band $band): bool
    {
        return !$this->demoMode && null !== $band->getId()
            && (string) $band->getId() === $this->bandId
            && '' !== $this->credentials() && [] !== $this->spectacles();
    }

    public function credentials(): string
    {
        $key = trim($this->apiKey);

        return preg_match('/[\r\n]/', $key) ? '' : $key;
    }

    /** @return list<int> */
    public function spectacles(): array
    {
        return $this->ids($this->spectacleIds);
    }

    public function isConfirmed(int $status): bool
    {
        return in_array($status, $this->ids($this->confirmedStatusIds), true);
    }

    public function cacheKey(): string
    {
        return 'orfeo.'.hash('sha256', implode(':', [
            $this->bandId, $this->apiKey, $this->spectacleIds, $this->confirmedStatusIds, $this->timezone,
        ]));
    }

    /** @return list<int> */
    private function ids(string $value): array
    {
        if (1 !== preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', trim($value))) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), explode(',', trim($value)))));
    }
}
