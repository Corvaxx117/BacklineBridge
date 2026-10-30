<?php

declare(strict_types=1);

namespace App\Service\Patch;

use App\Entity\PatchLine;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PatchLineArchiver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function archive(PatchLine $line): void
    {
        if ($line->isActive()) {
            $line->archive();
            $this->entityManager->flush();
        }
    }

    public function restore(PatchLine $line): bool
    {
        if ($line->isActive()) {
            return true;
        }
        $line->restore();
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Le canal a été réutilisé : la transaction conserve la source dans la corbeille.
            return false;
        }

        return true;
    }
}
