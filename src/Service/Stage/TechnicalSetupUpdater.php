<?php

declare(strict_types=1);

namespace App\Service\Stage;

use App\Dto\Stage\EquipmentInput;
use App\Dto\Stage\EquipmentItemInput;
use App\Dto\Stage\StageItemInput;
use App\Dto\Stage\StagePlanInput;
use App\Entity\TechnicalSheet;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TechnicalSetupUpdater
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function stage(TechnicalSheet $sheet, StagePlanInput $input): void
    {
        $sheet->setStagePlan(
            array_map(static function (StageItemInput $item): array {
                assert(null !== $item->kind && null !== $item->x && null !== $item->y);
                assert(null !== $item->rotation && null !== $item->scale);
                $data = [
                    'label' => trim($item->label),
                    'kind' => $item->kind,
                    'x' => $item->x,
                    'y' => $item->y,
                    'notes' => trim($item->notes),
                ];
                $rotation = fmod($item->rotation, 360.0);
                // Les valeurs par défaut restent implicites pour préserver les anciens plans et leurs empreintes.
                if (0.0 !== $rotation) {
                    $data['rotation'] = $rotation;
                }
                if (1.0 !== $item->scale) {
                    $data['scale'] = $item->scale;
                }
                if ([] !== $item->patchSources) {
                    $data['patchSources'] = array_values(array_unique($item->patchSources));
                }

                return $data;
            }, $input->items),
        );
        $this->em->flush();
    }

    public function equipment(TechnicalSheet $sheet, EquipmentInput $input): void
    {
        $sheet->setEquipmentRequests(
            array_map(static function (EquipmentItemInput $item): array {
                assert(null !== $item->quantity);

                return [
                    'id' => $item->id ?: bin2hex(random_bytes(12)),
                    'category' => trim($item->category),
                    'label' => trim($item->label),
                    'quantity' => $item->quantity,
                    'specification' => trim($item->specification),
                ];
            }, $input->items),
        );
        $this->em->flush();
    }
}
