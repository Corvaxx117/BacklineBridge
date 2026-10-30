<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Band;
use App\Enum\TreasuryEntryKind;
use App\Repository\TreasuryRepository;

final readonly class TreasuryView
{
    public function __construct(private TreasuryRepository $treasury)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Band $band): array
    {
        $first = new \DateTimeImmutable(
            'first day of this month 00:00:00',
            new \DateTimeZone('Europe/Paris'),
        )->modify('-5 months');
        $months = [];
        for ($i = 0; $i < 6; ++$i) {
            $date = $first->modify('+'.$i.' months');
            $months[$date->format('Y-m')] = [
                'label' => $date->format('m/Y'),
                'income' => 0,
                'expense' => 0,
            ];
        }
        foreach ($this->treasury->chartEntries($band, $first) as $entry) {
            $key = $entry->getOccurredOn()->format('Y-m');
            if (isset($months[$key])) {
                $months[$key][
                    TreasuryEntryKind::Income === $entry->getKind() ? 'income' : 'expense'
                ] += $entry->getAmountCents();
            }
        }
        $accounts = $this->treasury->accounts($band);
        $income = array_sum(array_column($months, 'income'));
        $expense = array_sum(array_column($months, 'expense'));

        return [
            'accounts' => $accounts,
            'total' => array_sum(array_map(static fn ($a): int => $a->getBalanceCents(), $accounts)),
            'months' => array_values($months),
            'income' => $income,
            'expense' => $expense,
            'maximum' => max(
                1,
                ...array_column($months, 'income'),
                ...array_column($months, 'expense'),
            ),
        ];
    }
}
