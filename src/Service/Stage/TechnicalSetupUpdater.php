<?php

declare(strict_types=1);

namespace App\Service\Stage;

use App\Dto\Stage\{StagePlanInput, EquipmentInput};
use App\Dto\Stage\StageItemInput;
use App\Entity\TechnicalSheet;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TechnicalSetupUpdater
{
    public function __construct(private EntityManagerInterface $em) {}

    public function stage(TechnicalSheet $sheet, StagePlanInput $input): void
    {
        $sheet->setStagePlan(
            array_map(static function (StageItemInput $item): array {
                $data = [
                    'label' => trim($item->label),
                    'kind' => $item->kind,
                    'x' => $item->x,
                    'y' => $item->y,
                    'notes' => trim($item->notes),
                ];
                $rotation = fmod($item->rotation, 360.0);
                // Les valeurs par défaut restent implicites pour préserver les anciens plans et leurs empreintes.
                if ($rotation !== 0.0) {
                    $data['rotation'] = $rotation;
                }
                if ($item->scale !== 1.0) {
                    $data['scale'] = $item->scale;
                }

                return $data;
            }, $input->items),
        );
        $this->em->flush();
    }

    public function equipment(TechnicalSheet $sheet, EquipmentInput $input): void
    {
        $sheet->setEquipmentRequests(
            array_map(
                static fn($item): array => [
                    'id' => $item->id ?: bin2hex(random_bytes(12)),
                    'category' => trim($item->category),
                    'label' => trim($item->label),
                    'quantity' => $item->quantity,
                    'specification' => trim($item->specification),
                ],
                $input->items,
            ),
        );
        $this->em->flush();
    }
}
