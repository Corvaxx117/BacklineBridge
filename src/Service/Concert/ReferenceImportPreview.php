<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;

final class ReferenceImportPreview
{
    public const LABELS = [
        'patch' => 'Patch des entrées',
        'stage' => 'Plan de scène',
        'equipment' => 'Matériel à prévoir',
        'notes' => 'Version et notes techniques',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forConcert(Concert $concert): array
    {
        $current = $this->snapshot($concert->getPatchSheet());
        $reference = $this->snapshot($concert->getBand()->getReferenceSheet());
        $sections = [];
        foreach (self::LABELS as $key => $label) {
            $sections[$key] = [
                'label' => $label,
                'changed' => $current[$key] !== $reference[$key],
                'current' => $current[$key],
                'reference' => $reference[$key],
            ];
        }

        return [
            'sections' => $sections,
            'hasChanges' => (bool) array_filter(
                $sections,
                static fn (array $section): bool => $section['changed'],
            ),
            'fingerprint' => hash(
                'sha256',
                json_encode([$concert->getId(), $current, $reference], JSON_THROW_ON_ERROR),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TechnicalSheet $sheet): array
    {
        $active = $sheet
            ->getLines()
            ->filter(static fn (PatchLine $line): bool => $line->isActive())
            ->getValues();
        usort(
            $active,
            static fn (PatchLine $a, PatchLine $b): int => $a->getChannelNumber() <=>
                $b->getChannelNumber(),
        );

        return [
            'patch' => array_map(
                static fn (PatchLine $line): array => [
                    'channel' => $line->getChannelNumber(),
                    'source' => $line->getSourceName(),
                    'microphone' => $line->getPreferredMicrophone(),
                    'stand' => $line->getStand(),
                    'notes' => $line->getNotes(),
                    'section' => $line->getSection(),
                ],
                $active,
            ),
            'stage' => array_map(
                static fn (array $item): array => [
                    'label' => $item['label'],
                    'kind' => $item['kind'],
                    'x' => (float) $item['x'],
                    'y' => (float) $item['y'],
                    'notes' => $item['notes'],
                    'rotation' => (float) ($item['rotation'] ?? 0),
                    'scale' => (float) ($item['scale'] ?? 1),
                ],
                $sheet->getStagePlan(),
            ),
            'equipment' => $sheet->getEquipmentRequests(),
            'notes' => [
                'bandName' => $sheet->getBandName(),
                'documentLabel' => $sheet->getDocumentLabel(),
                'notices' => $sheet->getNotices(),
            ],
        ];
    }
}
