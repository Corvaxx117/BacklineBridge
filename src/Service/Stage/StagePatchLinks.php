<?php

declare(strict_types=1);

namespace App\Service\Stage;

use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;

/** @phpstan-import-type StageElement from TechnicalSheet */
final class StagePatchLinks
{
    /** @return array<string, array{label: string, channel: ?int, available: bool}> */
    public function choices(TechnicalSheet $sheet): array
    {
        $lines = $sheet->getLines()
            ->filter(static fn (PatchLine $line): bool => $line->isActive())
            ->getValues();
        usort($lines, static fn (PatchLine $a, PatchLine $b): int => $a->getChannelNumber() <=> $b->getChannelNumber());
        $choices = [];
        foreach ($lines as $line) {
            $key = $line->getSourceKey();
            if (null !== $key) {
                $choices[$key] = [
                    'label' => sprintf('%02d · %s', $line->getChannelNumber(), $line->getSourceName()),
                    'channel' => $line->getChannelNumber(),
                    'available' => true,
                ];
            }
        }
        $missing = 0;
        foreach ($sheet->getStagePlan() as $item) {
            foreach ($item['patchSources'] ?? [] as $key) {
                if (!isset($choices[$key])) {
                    $choices[$key] = [
                        'label' => 'Source retirée du patch · liaison '.++$missing,
                        'channel' => null,
                        'available' => false,
                    ];
                }
            }
        }

        return $choices;
    }

    /** @return list<array<string, mixed>> */
    public function present(TechnicalSheet $sheet): array
    {
        $choices = $this->choices($sheet);

        return array_map(static function (array $item) use ($choices): array {
            $channels = [];
            $missing = 0;
            foreach ($item['patchSources'] ?? [] as $key) {
                $channel = $choices[$key]['channel'] ?? null;
                if (null === $channel) {
                    ++$missing;
                } else {
                    $channels[] = $channel;
                }
            }
            unset($item['patchSources']);
            if ([] !== $channels || $missing > 0) {
                sort($channels);
                $label = [] === $channels ? '' : (1 === count($channels) ? 'Entrée ' : 'Entrées ').implode(', ', $channels);
                $warning = $missing > 0 ? $missing.' source'.($missing > 1 ? 's retirées' : ' retirée') : '';
                $item['patchLabel'] = implode(' · ', array_filter([$label, $warning]));
                $item['patchMissing'] = $missing;
            }

            return $item;
        }, $sheet->getStagePlan());
    }

    /**
     * Les identités sont conservées lors des nouvelles copies. Pour une ancienne
     * copie, on rapproche le canal et le nom, puis un nom non ambigu dans la même
     * section si les canaux ont été renumérotés.
     *
     * @return list<StageElement>
     */
    public function copyPlan(TechnicalSheet $reference, TechnicalSheet $target): array
    {
        $targetByKey = $targetBySource = $targetByName = [];
        foreach ($target->getLines() as $line) {
            $key = $line->getSourceKey();
            if ($line->isActive() && null !== $key) {
                $targetByKey[$key] = $key;
                $targetBySource[$line->getChannelNumber().'|'.$line->getSourceName()] = $key;
                $name = $line->getSection().'|'.$line->getSourceName();
                $targetByName[$name] = array_key_exists($name, $targetByName) ? null : $key;
            }
        }
        $mapping = [];
        foreach ($reference->getLines() as $line) {
            $key = $line->getSourceKey();
            if ($line->isActive() && null !== $key) {
                $mapping[$key] = $targetByKey[$key]
                    ?? $targetBySource[$line->getChannelNumber().'|'.$line->getSourceName()]
                    ?? $targetByName[$line->getSection().'|'.$line->getSourceName()]
                    ?? $key;
            }
        }

        return array_map(static function (array $item) use ($mapping): array {
            if (isset($item['patchSources'])) {
                $item['patchSources'] = array_map(
                    static fn (string $key): string => $mapping[$key] ?? $key,
                    $item['patchSources'],
                );
            }

            return $item;
        }, $reference->getStagePlan());
    }
}
