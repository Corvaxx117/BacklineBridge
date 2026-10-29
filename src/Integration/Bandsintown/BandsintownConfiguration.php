<?php

declare(strict_types=1);

namespace App\Integration\Bandsintown;

use App\Entity\Band;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class BandsintownConfiguration
{
    public function __construct(
        #[Autowire('%env(BANDSINTOWN_BAND_ID)%')] private string $bandId,
        #[Autowire('%env(BANDSINTOWN_ARTIST_ID)%')] public string $artistId,
        #[Autowire('%env(BANDSINTOWN_APP_ID)%')] #[\SensitiveParameter] private string $appId,
    ) {}

    public function isConfiguredFor(Band $band): bool
    {
        return $band->getId() !== null &&
            (string) $band->getId() === $this->bandId &&
            preg_match('/^[1-9][0-9]{0,29}$/D', $this->artistId) === 1 &&
            trim($this->appId) !== '';
    }

    public function credentials(): string
    {
        return trim($this->appId);
    }

    public function cacheKey(): string
    {
        return 'bandsintown.' .
            hash('sha256', $this->bandId . ':' . $this->artistId . ':' . $this->appId);
    }
}
