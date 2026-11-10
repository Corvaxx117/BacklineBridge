<?php

declare(strict_types=1);

namespace App\Integration\Orfeo;

use App\Integration\Orfeo\Dto\OrfeoContract;
use App\Service\Money\EuroAmount;

final class OrfeoConditionsNormalizer
{
    /** @param array<string, mixed> $project
     * @return list<OrfeoContract> */
    public function contracts(array $project): array
    {
        $contracts = [];
        $rows = $project['contracts'] ?? [];
        if (!is_array($rows)) {
            return [];
        }
        foreach (array_slice($rows, 0, 10) as $row) {
            if (!is_array($row) || false === filter_var($row['pk'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                continue;
            }
            $contracts[] = new OrfeoContract(
                (int) $row['pk'],
                $this->text($row['contract_type']['name'] ?? '', 120) ?: 'Contrat',
                $this->text($row['status'] ?? '', 100),
                $this->notes($row, 'accommodation'),
                $this->notes($row, 'journey'),
                $this->notes($row, 'meal'),
                $this->amount($row['total_ht'] ?? null),
                $this->amount($row['total_ttc'] ?? null),
            );
        }

        return $contracts;
    }

    /** @param array<string, mixed> $contract */
    private function notes(array $contract, string $kind): string
    {
        $payer = match ($contract[$kind.'_paid_by'] ?? '') {
            'organizer' => 'Prise en charge : organisateur.',
            'producer' => 'Prise en charge : production.',
            default => '',
        };
        $notes = $this->text($contract[$kind.'_notes'] ?? '', 3900);

        return implode("\n", array_filter([$payer, $notes], static fn (string $text): bool => '' !== $text));
    }

    private function amount(mixed $value): ?int
    {
        if (!is_string($value)) {
            return null;
        }
        try {
            return EuroAmount::toCents(trim($value));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function text(mixed $value, int $limit): string
    {
        if (!is_string($value)) {
            return '';
        }
        $text = preg_replace('~<(?:br\s*/?|/p|/div|/li)>~i', "\n", $value) ?? '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text));

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }
}
