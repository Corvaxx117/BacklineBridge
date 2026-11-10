<?php

declare(strict_types=1);

namespace App\Service\Technical;

use App\Dto\Technical\TechnicalContactsInput;
use App\Dto\Technical\TechnicalIdentity;
use App\Dto\Technical\TechnicalNotesInput;
use App\Entity\TechnicalSheet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class TechnicalInformationUpdater
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function contacts(TechnicalSheet $sheet, TechnicalContactsInput $input): void
    {
        $band = $sheet->getBand() ?? throw new \LogicException('La fiche doit appartenir à un groupe.');
        $this->entityManager->getConnection()->transactional(function () use ($sheet, $band, $input): void {
            $this->entityManager->refresh($band);
            $this->checkVersion(TechnicalContactsInput::fingerprint($sheet), $input->revision);
            $identity = $band->getTechnicalIdentity()->toArray();
            $identity['contacts'] = $input->values();
            $band->setTechnicalIdentity(TechnicalIdentity::fromArray($identity));
            $this->entityManager->flush();
        });
    }

    public function notes(TechnicalSheet $sheet, TechnicalNotesInput $input): void
    {
        $band = $sheet->getBand() ?? throw new \LogicException('La fiche doit appartenir à un groupe.');
        $this->entityManager->getConnection()->transactional(function () use ($sheet, $band, $input): void {
            $this->entityManager->refresh($sheet);
            $this->entityManager->refresh($band);
            $this->checkVersion(TechnicalNotesInput::fingerprint($sheet), $input->revision);
            $identity = $band->getTechnicalIdentity()->toArray();
            $identity['style'] = $input->style;
            $identity['description'] = $input->description;
            $band->setTechnicalIdentity(TechnicalIdentity::fromArray($identity));
            $sheet->setNotices($input->values());
            $this->entityManager->flush();
        });
    }

    private function checkVersion(string $current, string $expected): void
    {
        if (!hash_equals($current, $expected)) {
            throw new ConflictHttpException(
                'Ces informations ont changé. Tes modifications restent affichées : conserve-les puis recharge la page.',
            );
        }
    }
}
