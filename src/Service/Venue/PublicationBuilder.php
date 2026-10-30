<?php

declare(strict_types=1);

namespace App\Service\Venue;

use App\Entity\Concert;
use App\Entity\TechnicalPublication;

final class PublicationBuilder
{
    /** @return array<string, mixed> */
    public function snapshot(Concert $concert): array
    {
        $sheet = $concert->getPatchSheet();
        $lines = [];
        foreach ($sheet->getLines() as $line) {
            if ($line->isActive()) {
                $lines[] = [
                    'id' => $line->getId(),
                    'channel' => $line->getChannelNumber(),
                    'source' => $line->getSourceName(),
                    'microphone' => $line->getPreferredMicrophone(),
                    'stand' => $line->getStand(),
                    'notes' => $line->getNotes(),
                ];
            }
        }
        usort($lines, static fn (array $a, array $b): int => $a['channel'] <=> $b['channel']);

        // Liste explicite : les conditions privées ne font jamais partie du dossier invité.
        return [
            'bandName' => $sheet->getBandName(),
            'date' => $concert->getDate()->format('Y-m-d'),
            'venue' => $concert->getVenue(),
            'city' => $concert->getCity(),
            'notes' => $concert->getNotes(),
            'notices' => $sheet->getNotices(),
            'lines' => $lines,
            'stagePlan' => $sheet->getStagePlan(),
            'equipment' => $sheet->getEquipmentRequests(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function isOutdated(TechnicalPublication $publication): bool
    {
        return $publication->getFingerprint() !==
            $this->fingerprint($this->snapshot($publication->getConcert()));
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, array{category: string, label: string, specification: string}>
     */
    public function requests(array $snapshot): array
    {
        $requests = [];
        foreach ($snapshot['equipment'] as $item) {
            $requests['gear_'.$item['id']] = [
                'category' => $item['category'],
                'label' => $item['quantity'].' × '.$item['label'],
                'specification' => $item['specification'],
            ];
        }
        foreach ($snapshot['lines'] as $line) {
            $requests['line_'.$line['id']] = [
                'category' => 'Patch des entrées',
                'label' => sprintf('%02d · %s', $line['channel'], $line['source']),
                'specification' => implode(
                    ' · ',
                    array_filter([$line['microphone'], $line['stand'], $line['notes']]),
                ),
            ];
        }

        return $requests;
    }
}
