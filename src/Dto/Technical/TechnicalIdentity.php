<?php

declare(strict_types=1);

namespace App\Dto\Technical;

/** Coordonnées explicitement destinées à la fiche technique, sans données administratives. */
final readonly class TechnicalIdentity
{
    /** @param list<array{name: string, role: string, email: string, phone: string}> $contacts */
    private function __construct(
        public string $style,
        public string $description,
        public array $contacts,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        self::assertKeys($data, ['style', 'description', 'contacts']);
        $style = self::text($data['style'] ?? '', 150);
        $description = self::text($data['description'] ?? '', 300);
        $contacts = $data['contacts'] ?? [];
        if (!is_array($contacts) || !array_is_list($contacts) || count($contacts) > 4) {
            throw new \DomainException('La fiche accepte au maximum quatre contacts techniques.');
        }
        $result = [];
        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                throw new \DomainException('Format de contact technique invalide.');
            }
            self::assertKeys($contact, ['name', 'role', 'email', 'phone']);
            $row = [
                'name' => self::text($contact['name'] ?? '', 100),
                'role' => self::text($contact['role'] ?? '', 100),
                'email' => self::text($contact['email'] ?? '', 180),
                'phone' => self::text($contact['phone'] ?? '', 40),
            ];
            if ('' === $row['name'] || '' === $row['role']
                || ('' !== $row['email'] && !filter_var($row['email'], FILTER_VALIDATE_EMAIL))
                || ('' !== $row['phone'] && 1 !== preg_match('/^[+0-9(). -]{3,40}$/D', $row['phone']))) {
                throw new \DomainException('Vérifie le nom, la fonction, l’e-mail et le téléphone du contact.');
            }
            $result[] = $row;
        }

        return new self($style, $description, $result);
    }

    /** @return array{style: string, description: string, contacts: list<array{name: string, role: string, email: string, phone: string}>} */
    public function toArray(): array
    {
        return ['style' => $this->style, 'description' => $this->description, 'contacts' => $this->contacts];
    }

    public function hasContent(): bool
    {
        return '' !== $this->style || '' !== $this->description || [] !== $this->contacts;
    }

    private static function text(mixed $value, int $limit): string
    {
        if (!is_string($value) || mb_strlen($value) > $limit || str_contains($value, "\0")) {
            throw new \DomainException('Un texte de la fiche technique est invalide ou trop long.');
        }

        return trim($value);
    }

    /** @param array<array-key, mixed> $data
     * @param list<string> $allowed */
    private static function assertKeys(array $data, array $allowed): void
    {
        if ([] !== array_diff(array_keys($data), $allowed)) {
            throw new \DomainException('Le fichier contient des champs qui ne sont pas destinés à une fiche technique.');
        }
    }
}
