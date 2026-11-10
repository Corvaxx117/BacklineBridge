<?php

declare(strict_types=1);

namespace App\Integration\Orfeo;

use App\Enum\ContactRole;
use App\Integration\Orfeo\Dto\OrfeoContact;
use App\Integration\Orfeo\Dto\OrfeoEvent;

final readonly class OrfeoEventNormalizer
{
    public function __construct(
        private OrfeoConfiguration $configuration,
        private OrfeoConditionsNormalizer $conditions = new OrfeoConditionsNormalizer(),
    ) {
    }

    /** @param array<string, mixed> $project
     * @param list<array<string, mixed>> $linkedContacts
     *
     * @return list<OrfeoEvent> */
    public function normalize(array $project, array $linkedContacts): array
    {
        $spectacle = (int) ($project['spectacle']['pk'] ?? 0);
        if (!in_array($spectacle, $this->configuration->spectacles(), true)) {
            throw new \UnexpectedValueException('Projet hors du périmètre configuré.');
        }
        if ($project['is_archived'] ?? false) {
            return [];
        }
        $place = is_array($project['venue'] ?? null) ? $project['venue'] : ($project['place'] ?? []);
        $venue = $this->text($place['name'] ?? '', 150);
        $city = $this->text($place['city'] ?? '', 120);
        $projectId = $this->id($project['pk'] ?? null);
        if ('' === $venue || '' === $city) {
            return [];
        }
        $contacts = $this->contacts($project, $linkedContacts);
        $status = $project['status'] ?? [];
        $events = [];
        foreach ($project['performances'] ?? [] as $performance) {
            try {
                if (!is_array($performance)) {
                    continue;
                }
                $id = $this->id($performance['pk'] ?? null);
                $raw = $performance['start_datetime'] ?? null;
                if (!is_string($raw) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/D', $raw)) {
                    continue;
                }
                $date = new \DateTimeImmutable($raw, new \DateTimeZone($this->configuration->timezone));
                if ($date->format('Y-m-d') !== substr($raw, 0, 10)) {
                    continue;
                }
                $date = $date->setTimezone(new \DateTimeZone($this->configuration->timezone));
                $events[] = new OrfeoEvent(
                    $projectId, $id, $spectacle, $date->format('Y-m-d'),
                    ($performance['is_all_day_event'] ?? false) ? null : $date->format('H:i'),
                    $venue, $city, $this->optional($place['address1'] ?? null, 200),
                    (int) ($status['pk'] ?? 0), $this->text($status['name'] ?? '', 60),
                    $this->configuration->isConfirmed((int) ($status['pk'] ?? 0)),
                    (bool) ($project['is_cancelled'] ?? false), $contacts,
                    $this->conditions->contracts($project),
                );
            } catch (\Exception) {
                continue;
            }
        }

        return $events;
    }

    /** @param array<string, mixed> $project
     * @param list<array<string, mixed>> $linked
     *
     * @return list<OrfeoContact> */
    private function contacts(array $project, array $linked): array
    {
        $contacts = [];
        foreach ($linked as $row) {
            if ((int) ($row['parent'] ?? 0) !== (int) $project['pk']) {
                continue;
            }
            $person = $row['structure_person']['person'] ?? [];
            $name = trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? ''));
            $title = $row['structure_person']['title'] ?? '';
            $organization = '' === $name;
            $name = $organization ? ($row['entity']['name'] ?? '') : $name;
            $infos = $organization ? ($row['entity']['contact_infos'] ?? []) : ($row['structure_person']['contact_infos'] ?? []);
            if ('' === trim($name)) {
                continue;
            }
            [$email, $phone] = $this->coordinates($infos);
            $role = $organization ? ContactRole::Organizer : $this->role((string) $title);
            $contacts[] = new OrfeoContact('linked:'.$this->id($row['pk'] ?? null),
                $this->text($name, 120), $role, $email, $phone, $this->optional($title, 500));
        }
        $place = $project['place'] ?? [];
        if (is_array($place)) {
            [$email, $phone] = $this->coordinates($place['contact_infos'] ?? []);
            $email ??= $this->email($place['signatory_email'] ?? null);
            $phone ??= $this->optional($place['signatory_phone'] ?? null, 40);
            $name = $this->text(($place['signatory_name'] ?? '') ?: ($place['name'] ?? ''), 120);
            if ('' !== $name && (null !== $email || null !== $phone)) {
                $contacts[] = new OrfeoContact('place:'.(int) ($place['pk'] ?? 0), $name,
                    ContactRole::Organizer, $email, $phone, null);
            }
        }

        return $contacts;
    }

    /** @param array<array<string, mixed>> $infos
     * @return array{?string, ?string} */
    private function coordinates(array $infos): array
    {
        $email = $phone = null;
        foreach ($infos as $info) {
            if ('mail' === ($info['type'] ?? '')) {
                $email ??= $this->email($info['value'] ?? null);
            } elseif ('phone' === ($info['type'] ?? '')) {
                $phone ??= $this->optional($info['value'] ?? null, 40);
            }
        }

        return [$email, $phone];
    }

    private function role(string $title): ContactRole
    {
        $title = mb_strtolower($title);

        return match (true) {
            1 === preg_match('/r[ée]gi|stage manager/u', $title) => ContactRole::StageManager,
            str_contains($title, 'tech') => ContactRole::Technician,
            str_contains($title, 'book'), str_contains($title, 'prod') => ContactRole::Booker,
            str_contains($title, 'orga') => ContactRole::Organizer,
            default => ContactRole::Other,
        };
    }

    private function id(mixed $value): int
    {
        if (false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new \UnexpectedValueException('Identifiant Orfeo invalide.');
        }

        return (int) $value;
    }

    private function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim(strip_tags($value)), 0, $max) : '';
    }

    private function optional(mixed $value, int $max): ?string
    {
        $text = $this->text($value, $max);

        return '' === $text ? null : $text;
    }

    private function email(mixed $value): ?string
    {
        return is_string($value) && mb_strlen($value) <= 180 && filter_var($value, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($value) : null;
    }
}
