<?php

declare(strict_types=1);

namespace App\Controller\Setlist;

use App\Dto\Setlist\ImportSetlistInput;
use App\Entity\Concert;
use App\Entity\User;
use App\Form\Setlist\ImportSetlistType;
use App\Repository\SetlistRepository;
use App\Security\Voter\BandVoter;
use App\Service\Setlist\SetlistManager;
use App\Service\Setlist\SetlistVersion;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/concerts/{id}/setlist/reference', name: 'app_setlist_import', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class ImportReferenceAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        #[CurrentUser] User $user,
        SetlistRepository $setlists,
        SetlistManager $manager,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::SETLIST, $concert->getBand());
        $referenceSheet = $concert->getBand()->getReferenceSheet();
        $sheet = $concert->getPatchSheet();
        $reference = $setlists->forSheet($referenceSheet);
        $current = $setlists->forSheet($sheet);
        $input = new ImportSetlistInput();
        $input->revision = SetlistVersion::fingerprint($sheet, $current);
        $input->referenceRevision = SetlistVersion::fingerprint($referenceSheet, $reference);
        $form = $this->createForm(ImportSetlistType::class, $input, [
            'require_confirmation' => [] !== ($current?->getTracks() ?? []),
            'csrf_token_id' => 'setlist_import_'.$concert->getId(),
        ])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->importReference($concert, $user, $input);
                $this->addFlash('success', 'La setlist de référence a été copiée pour ce concert.');

                return $this->redirectToRoute('app_concert_show', ['id' => $concert->getId(), '_fragment' => 'setlist'], 303);
            } catch (ConflictHttpException|OptimisticLockException|UniqueConstraintViolationException|RetryableException $exception) {
                $message = $exception instanceof ConflictHttpException
                    ? $exception->getMessage()
                    : 'Une setlist a changé. Recharge l’aperçu avant de confirmer.';
                $form->addError(new FormError($message));
                $status = 409;
            }
        }

        return $this->render('setlist/import.html.twig', [
            'sheet' => $sheet,
            'concert' => $concert,
            'current' => $current,
            'reference' => $reference,
            'form' => $form->createView(),
            'conflict' => 409 === $status,
        ], new Response(status: $status));
    }
}
