<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\ReferenceImportInput;
use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Service\Stage\StagePatchLinks;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ReferenceImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReferenceImportPreview $previews,
        private StagePatchLinks $stageLinks,
    ) {
    }

    public function import(Concert $concert, ReferenceImportInput $input): void
    {
        $preview = $this->previews->forConcert($concert);
        if (!hash_equals($preview['fingerprint'], $input->fingerprint)) {
            throw new \DomainException(
                'Le dossier ou la référence a changé depuis l’aperçu. Recharge-le avant de confirmer.',
            );
        }
        if (
            !$input->confirmReplacement
            || [] === $input->sections
            || [] !== array_diff($input->sections, array_keys(ReferenceImportPreview::LABELS))
        ) {
            throw new \InvalidArgumentException('Choix d’import invalides.');
        }
        foreach ($input->sections as $section) {
            if (!$preview['sections'][$section]['changed']) {
                throw new \DomainException(
                    'Une section choisie est déjà identique. Recharge l’aperçu.',
                );
            }
        }

        $reference = $concert->getBand()->getReferenceSheet();
        $sheet = $concert->getPatchSheet();
        $this->entityManager->wrapInTransaction(function () use ($input, $reference, $sheet): void {
            if (in_array('patch', $input->sections, true)) {
                $this->replacePatch($reference, $sheet);
            }
            foreach ($input->sections as $section) {
                match ($section) {
                    'patch' => null,
                    'stage' => $sheet->setStagePlan($this->stageLinks->copyPlan($reference, $sheet)),
                    'equipment' => $sheet->setEquipmentRequests($reference->getEquipmentRequests()),
                    'notes' => $sheet
                        ->setBandName($reference->getBandName())
                        ->setDocumentLabel($reference->getDocumentLabel())
                        ->setNotices($reference->getNotices()),
                    default => throw new \DomainException('Section de référence inconnue.'),
                };
            }
        });
    }

    private function replacePatch(TechnicalSheet $reference, TechnicalSheet $sheet): void
    {
        $linkedPlan = $this->stageLinks->copyPlan($sheet, $reference);
        $existing = [];
        foreach ($sheet->getLines() as $line) {
            if ($line->isActive()) {
                $existing[$line->getChannelNumber()] = $line;
            }
        }
        foreach ($reference->getLines() as $source) {
            if (!$source->isActive()) {
                continue;
            }
            $channel = $source->getChannelNumber();
            $line = $existing[$channel] ?? new PatchLine()->inheritSourceKey($source);
            $line
                ->inheritSourceKey($source)
                ->setChannelNumber($channel)
                ->setSourceName($source->getSourceName())
                ->setPreferredMicrophone($source->getPreferredMicrophone())
                ->setStand($source->getStand())
                ->setNotes($source->getNotes())
                ->setSection($source->getSection());
            $sheet->addLine($line);
            unset($existing[$channel]);
        }
        // Les sources propres au concert ne sont jamais effacées définitivement.
        foreach ($existing as $line) {
            $line->archive();
        }
        $sheet->setStagePlan($linkedPlan);
    }
}
