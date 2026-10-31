<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\Concert;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ConcertRemover
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function remove(Concert $concert, string $revision): void
    {
        if ($revision !== (string) $concert->getRevision()) {
            throw new ConflictHttpException('Ce concert a changé. Relis-le avant de le supprimer.');
        }
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        if ($sheetId === $concert->getBand()->getReferenceSheet()->getId()) {
            throw new \LogicException('La référence du groupe ne doit jamais être supprimée.');
        }

        // Les relations historiques n’ont pas toutes de cascade SQL.
        // Une seule transaction retire le dossier et ses accès, jamais la référence du groupe.
        $this->entityManager->getConnection()->transactional(function (Connection $connection) use ($id, $sheetId, $revision): void {
            $connection->executeStatement(
                <<<'SQL'
                    DELETE FROM venue_reply WHERE invitation_id IN (
                        SELECT invitation.id FROM venue_invitation invitation
                        JOIN technical_publication publication ON publication.id = invitation.publication_id
                        WHERE publication.concert_id = ?
                    )
                    SQL,
                [$id],
            );
            $connection->executeStatement(
                <<<'SQL'
                    DELETE FROM venue_invitation WHERE publication_id IN (
                        SELECT id FROM technical_publication WHERE concert_id = ?
                    )
                    SQL,
                [$id],
            );
            foreach ([
                'technical_publication',
                'bandsintown_concert',
                'concert_conditions',
                'concert_availability',
            ] as $table) {
                $connection->executeStatement('DELETE FROM '.$table.' WHERE concert_id = ?', [$id]);
            }
            $deleted = $connection->executeStatement(
                'DELETE FROM concert WHERE id = ? AND revision = ?',
                [$id, (int) $revision],
            );
            if (1 !== $deleted) {
                throw new ConflictHttpException('Ce concert a été modifié pendant la suppression.');
            }
            // Les contacts sont supprimés par cascade ; les documents sont conservés sans concert.
            $connection->executeStatement('DELETE FROM patch_line WHERE sheet_id = ?', [$sheetId]);
            $connection->executeStatement('DELETE FROM technical_sheet WHERE id = ?', [$sheetId]);
        });
        $this->entityManager->clear();
    }
}
