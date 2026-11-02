<?php

declare(strict_types=1);

namespace App\Service\Setlist;

use App\Dto\Setlist\ImportSetlistInput;
use App\Dto\Setlist\SetlistInput;
use App\Entity\Concert;
use App\Entity\Setlist;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Repository\SetlistRepository;
use App\Service\Finance\MusicianAccess;
use App\ValueObject\SetlistContent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class SetlistManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SetlistRepository $setlists,
        private MusicianAccess $musicians,
    ) {
    }

    public function save(TechnicalSheet $sheet, User $author, SetlistInput $input): void
    {
        $this->assertMusician($sheet, $author);
        $current = $this->setlists->forSheet($sheet);
        $this->checkVersion($sheet, $current, $input->revision);
        $this->replace($sheet, $current, SetlistContent::fromText($input->songs), $author);
        $this->entityManager->flush();
    }

    public function importReference(Concert $concert, User $author, ImportSetlistInput $input): void
    {
        $sheet = $concert->getPatchSheet();
        $this->assertMusician($sheet, $author);
        $reference = $concert->getBand()->getReferenceSheet();
        // Les lectures et la copie partagent la même transaction SQLite.
        $this->entityManager->getConnection()->transactional(function () use ($reference, $sheet, $author, $input): void {
            $source = $this->setlists->forSheet($reference);
            $current = $this->setlists->forSheet($sheet);
            foreach ([$source, $current] as $record) {
                if (null !== $record) {
                    $this->entityManager->refresh($record);
                }
            }
            $this->checkVersion($reference, $source, $input->referenceRevision);
            $this->checkVersion($sheet, $current, $input->revision);
            if (null === $source || [] === $source->getTracks()) {
                throw new ConflictHttpException('La setlist de référence est vide. Complète-la avant de l’importer.');
            }
            if (null !== $current && [] !== $current->getTracks() && !$input->confirmReplacement) {
                throw new ConflictHttpException('Confirme le remplacement de la setlist de ce concert.');
            }
            $this->replace($sheet, $current, SetlistContent::fromTracks($source->getTracks()), $author);
            $this->entityManager->flush();
        });
    }

    private function replace(TechnicalSheet $sheet, ?Setlist $current, SetlistContent $content, User $author): void
    {
        if (null === $current) {
            $this->entityManager->persist(new Setlist($sheet, $content, $author));
        } else {
            $current->replace($content, $author);
        }
    }

    private function assertMusician(TechnicalSheet $sheet, User $user): void
    {
        $band = $sheet->getBand();
        if (null === $band || !$this->musicians->allows($user, $band)) {
            throw new AccessDeniedException('Seuls les musiciens du groupe peuvent modifier une setlist.');
        }
    }

    private function checkVersion(TechnicalSheet $sheet, ?Setlist $current, ?string $expected): void
    {
        if (null === $expected || !hash_equals(SetlistVersion::fingerprint($sheet, $current), $expected)) {
            throw new ConflictHttpException(
                'Une setlist a changé depuis l’ouverture de la page. Recharge la version actuelle avant de réessayer.',
            );
        }
    }
}
