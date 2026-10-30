<?php

declare(strict_types=1);

namespace App\Integration\Bandsintown;

use App\Integration\Bandsintown\Dto\BandsintownEvent;

final class BandsintownEventNormalizer
{
    /**
     * @param array<string, mixed> $data
     */
    public function normalize(array $data, string $artistId): BandsintownEvent
    {
        $rawId = $data['id'] ?? null;
        if (!is_string($rawId) && !is_int($rawId)) {
            throw new \UnexpectedValueException('Identifiant d’annonce invalide.');
        }
        $id = (string) $rawId;
        if (
            1 !== preg_match('/^[1-9][0-9]{0,29}$/D', $id)
            || (isset($data['artist_id'])
                && ((!is_string($data['artist_id']) && !is_int($data['artist_id']))
                    || (string) $data['artist_id'] !== $artistId))
        ) {
            throw new \UnexpectedValueException('Annonce ou artiste invalide.');
        }
        if (
            ($data['cancelled'] ?? false) === true
            || ($data['canceled'] ?? false) === true
            || in_array($data['status'] ?? '', ['cancelled', 'canceled'], true)
            || ($data['streaming_event'] ?? false) === true
        ) {
            throw new \UnexpectedValueException('Annonce annulée ou diffusée en ligne.');
        }
        $datetime = $data['datetime'] ?? '';
        if (
            !is_string($datetime)
            || 1 !==
                preg_match(
                    '/^(\d{4}-\d{2}-\d{2})T([0-2][0-9]):([0-5][0-9]):([0-5][0-9])(?:\.\d{1,6})?(?:Z|[+-][0-2][0-9]:[0-5][0-9])?$/D',
                    $datetime,
                    $match,
                )
            || (int) $match[2] > 23
        ) {
            throw new \UnexpectedValueException('Date ou heure invalide.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $match[1], new \DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $match[1]) {
            throw new \UnexpectedValueException('Date invalide.');
        }
        $venue = $data['venue'] ?? [];
        if (!is_array($venue)) {
            throw new \UnexpectedValueException('Salle invalide.');
        }
        $name = $this->text($venue['name'] ?? '', 150);
        $city = $this->text($venue['city'] ?? '', 120);
        if ('' === $name || '' === $city) {
            throw new \UnexpectedValueException('Salle ou ville manquante.');
        }
        $lineup = [];
        foreach (is_array($data['lineup'] ?? null) ? $data['lineup'] : [] as $artist) {
            if (is_string($artist) && ($nameInLineup = $this->text($artist, 150)) !== '') {
                $lineup[] = $nameInLineup;
            }
            if (50 === count($lineup)) {
                break;
            }
        }
        $tickets = [];
        foreach (is_array($data['offers'] ?? null) ? $data['offers'] : [] as $offer) {
            if (is_array($offer) && ($url = $this->safeUrl($offer['url'] ?? null)) !== null) {
                $tickets[] = [
                    'label' => $this->text($offer['type'] ?? 'Billetterie', 60) ?: 'Billetterie',
                    'url' => $url,
                ];
            }
            if (10 === count($tickets)) {
                break;
            }
        }

        return new BandsintownEvent(
            $id,
            $artistId,
            $match[1],
            $match[2].':'.$match[3],
            $name,
            $city,
            $this->text($venue['country'] ?? '', 100),
            $this->text($venue['street_address'] ?? '', 300),
            $this->text($data['title'] ?? '', 250),
            $this->text($data['description'] ?? '', 10000),
            array_values(array_unique($lineup)),
            $tickets,
        );
    }

    private function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim(strip_tags($value)), 0, $max) : '';
    }

    private function safeUrl(mixed $value): ?string
    {
        if (
            !is_string($value)
            || strlen($value) > 2000
            || !filter_var($value, FILTER_VALIDATE_URL)
        ) {
            return null;
        }
        $parts = parse_url($value);
        if (
            ($parts['scheme'] ?? '') !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        unset($query['app_id']);

        return 'https://'.
            $parts['host'].
            (isset($parts['port']) ? ':'.$parts['port'] : '').
            ($parts['path'] ?? '').
            ([] !== $query ? '?'.http_build_query($query) : '').
            (isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
