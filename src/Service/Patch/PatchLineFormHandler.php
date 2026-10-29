<?php

declare(strict_types=1);

namespace App\Service\Patch;

use App\Entity\PatchLine;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

final readonly class PatchLineFormHandler
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /** Le contrôleur a déjà appelé handleRequest() sur le formulaire. */
    public function handle(FormInterface $form, PatchLine $line): FormSubmissionResult
    {
        if (!$form->isSubmitted()) {
            return FormSubmissionResult::NotSubmitted;
        }

        if (!$form->isValid()) {
            return FormSubmissionResult::Invalid;
        }

        try {
            if ($line->getId() === null) {
                $this->entityManager->persist($line);
            }

            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Doctrine ferme l'EntityManager après la collision : aucune nouvelle tentative d'enregistrement ici.
            $form
                ->get('channelNumber')
                ->addError(
                    new FormError('Ce numéro de canal vient d’être utilisé. Choisis-en un autre.'),
                );

            return FormSubmissionResult::Conflict;
        }

        return FormSubmissionResult::Saved;
    }
}
