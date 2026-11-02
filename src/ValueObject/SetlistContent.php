<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class SetlistContent
{
    public const MAX_TRACKS = 100;
    public const MAX_TITLE_LENGTH = 160;
    public const MAX_TEXT_LENGTH = 20000;

    /** @param list<string> $tracks */
    private function __construct(private array $tracks)
    {
    }

    public static function fromText(string $text): self
    {
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new \InvalidArgumentException('La setlist est trop longue.');
        }
        $lines = preg_split('/\R/u', $text);
        if (false === $lines) {
            throw new \InvalidArgumentException('Le texte contient des caractères invalides.');
        }

        return self::fromTracks($lines);
    }

    /** @param list<string> $tracks */
    public static function fromTracks(array $tracks): self
    {
        $tracks = array_values(array_filter(array_map(trim(...), $tracks), static fn (string $track): bool => '' !== $track));
        if (count($tracks) > self::MAX_TRACKS) {
            throw new \InvalidArgumentException('Une setlist peut contenir 100 titres au maximum.');
        }
        foreach ($tracks as $track) {
            if (mb_strlen($track) > self::MAX_TITLE_LENGTH) {
                throw new \InvalidArgumentException('Chaque titre peut contenir 160 caractères au maximum.');
            }
            if (preg_match('/\R/u', $track)) {
                throw new \InvalidArgumentException('Indique un seul morceau par ligne.');
            }
        }

        return new self($tracks);
    }

    /** @return list<string> */
    public function tracks(): array
    {
        return $this->tracks;
    }
}
