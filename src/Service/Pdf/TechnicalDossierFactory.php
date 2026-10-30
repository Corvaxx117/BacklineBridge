<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use App\Dto\Pdf\TechnicalDossier;
use App\Entity\PatchLine;
use App\Entity\TechnicalPublication;
use App\Entity\TechnicalSheet;
use App\Repository\PatchLineRepository;
use App\Service\Venue\PublicationBuilder;

final readonly class TechnicalDossierFactory
{
    public function __construct(
        private PatchLineRepository $lines,
        private PublicationBuilder $publications,
    ) {
    }

    public function forSheet(TechnicalSheet $sheet): TechnicalDossier
    {
        if (null !== $sheet->getConcert()) {
            return $this->fromSnapshot(
                $this->publications->snapshot($sheet->getConcert()),
                'Dossier du concert',
                documentLabel: $sheet->getDocumentLabel(),
            );
        }

        return new TechnicalDossier(
            bandName: $sheet->getBandName(),
            label: 'Fiche de référence',
            date: null,
            venue: null,
            city: null,
            notes: null,
            notices: $sheet->getNotices(),
            lines: array_map(
                static fn (PatchLine $line): array => [
                    'channel' => $line->getChannelNumber(),
                    'source' => $line->getSourceName(),
                    'microphone' => $line->getPreferredMicrophone(),
                    'stand' => $line->getStand(),
                    'notes' => $line->getNotes(),
                ],
                $this->lines->findForSheet($sheet),
            ),
            stagePlan: $sheet->getStagePlan(),
            equipment: $sheet->getEquipmentRequests(),
            documentLabel: $sheet->getDocumentLabel(),
        );
    }

    public function forPublication(TechnicalPublication $publication): TechnicalDossier
    {
        // Ne jamais relire la fiche actuelle pour un invité : son document reste figé.
        return $this->fromSnapshot(
            $publication->getSnapshot(),
            'Dossier publié #'.$publication->getId(),
            $publication->getPublishedAt(),
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function fromSnapshot(
        array $snapshot,
        string $label,
        ?\DateTimeImmutable $publishedAt = null,
        ?string $documentLabel = null,
    ): TechnicalDossier {
        return new TechnicalDossier(
            bandName: $snapshot['bandName'],
            label: $label,
            date: $snapshot['date'],
            venue: $snapshot['venue'],
            city: $snapshot['city'],
            notes: $snapshot['notes'],
            notices: $snapshot['notices'],
            lines: $snapshot['lines'],
            stagePlan: $snapshot['stagePlan'],
            equipment: $snapshot['equipment'],
            publishedAt: $publishedAt,
            documentLabel: $documentLabel,
        );
    }
}
