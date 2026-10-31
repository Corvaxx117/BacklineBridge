<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Service\Stage\StagePatchLinks;

final class ReferenceImportPreview
{
    public function __construct(private readonly StagePatchLinks $stageLinks)
    {
    }
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
        $currentSheet = $concert->getPatchSheet();
        $referenceSheet = $concert->getBand()->getReferenceSheet();
        $current = $this->snapshot($concert->getPatchSheet());
        $reference = $this->snapshot($concert->getBand()->getReferenceSheet());
        $stageChanged = $this->stageSignature($this->stageLinks->copyPlan($currentSheet, $referenceSheet))
            !== $this->stageSignature($referenceSheet->getStagePlan());
        $sections = [];
        foreach (self::LABELS as $key => $label) {
            $sections[$key] = [
                'label' => $label,
                'changed' => 'stage' === $key ? $stageChanged : $current[$key] !== $reference[$key],
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
                json_encode([
                    $concert->getId(), $current, $reference,
                    $concert->getPatchSheet()->getStagePlan(),
                    $concert->getBand()->getReferenceSheet()->getStagePlan(),
                ], JSON_THROW_ON_ERROR),
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function stageSignature(array $items): array
    {
        return array_map(static function (array $item): array {
            $sources = $item['patchSources'] ?? [];
            sort($sources);

            return [
                'label' => $item['label'],
                'kind' => $item['kind'],
                'x' => (float) $item['x'],
                'y' => (float) $item['y'],
                'notes' => $item['notes'],
                'rotation' => (float) ($item['rotation'] ?? 0),
                'scale' => (float) ($item['scale'] ?? 1),
                'patchSources' => $sources,
            ];
        }, $items);
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
                    'patchLabel' => $item['patchLabel'] ?? '',
                    'patchMissing' => $item['patchMissing'] ?? 0,
                ],
                $this->stageLinks->present($sheet),
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
