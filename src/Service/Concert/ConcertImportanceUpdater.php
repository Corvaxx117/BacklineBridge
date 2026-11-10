<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\ConcertImportanceInput;
use App\Entity\Concert;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ConcertImportanceUpdater
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function update(Concert $concert, ConcertImportanceInput $input): void
    {
        if ($input->revision !== (string) $concert->getRevision()) {
            throw new ConflictHttpException('Le concert a changé. Recharge ses informations avant de réessayer.');
        }
        $concert->setImportance($input->level, $input->note);
        $this->em->flush();
    }
}
