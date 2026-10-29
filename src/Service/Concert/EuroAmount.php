<?php

declare(strict_types=1);

namespace App\Service\Concert;

/** Montants EUR stockés en centimes, sans conversion intermédiaire en nombre flottant. */
final class EuroAmount
{
    public static function toCents(?string $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        if (!preg_match('/^\d{1,7}(?:[,.]\d{1,2})?$/D', $amount)) {
            throw new \InvalidArgumentException('Montant EUR invalide.');
        }
        [$units, $decimals] = array_pad(explode('.', str_replace(',', '.', $amount)), 2, '');

        return (int) $units * 100 + (int) str_pad($decimals, 2, '0');
    }

    public static function fromCents(?int $cents): ?string
    {
        return $cents === null
            ? null
            : intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
