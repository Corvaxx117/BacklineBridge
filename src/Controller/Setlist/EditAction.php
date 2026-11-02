<?php

declare(strict_types=1);

namespace App\Controller\Setlist;

use App\Dto\Setlist\SetlistInput;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Form\Setlist\SetlistType;
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

#[Route('/fiches/{id}/setlist', name: 'app_setlist_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] TechnicalSheet $sheet,
        #[CurrentUser] User $user,
        SetlistRepository $setlists,
        SetlistManager $manager,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::SETLIST, $sheet->getBand());
        $current = $setlists->forSheet($sheet);
        $input = new SetlistInput();
        $input->songs = implode("\n", $current?->getTracks() ?? []);
        $input->revision = SetlistVersion::fingerprint($sheet, $current);
        $form = $this->createForm(SetlistType::class, $input, [
            'csrf_token_id' => 'setlist_edit_'.$sheet->getId(),
        ])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->save($sheet, $user, $input);
                $this->addFlash('success', 'La setlist a été enregistrée.');
                $concert = $sheet->getConcert();

                return null === $concert
                    ? $this->redirectToRoute('app_patch_index', ['_fragment' => 'setlist'], 303)
                    : $this->redirectToRoute('app_concert_show', ['id' => $concert->getId(), '_fragment' => 'setlist'], 303);
            } catch (ConflictHttpException|OptimisticLockException|UniqueConstraintViolationException|RetryableException) {
                $form->addError(new FormError(
                    'La setlist a changé. Tes titres restent affichés : conserve-les puis recharge la version actuelle.',
                ));
                $status = 409;
            }
        }

        return $this->render('setlist/edit.html.twig', [
            'sheet' => $sheet,
            'form' => $form->createView(),
            'conflict' => 409 === $status,
        ], new Response(status: $status));
    }
}
