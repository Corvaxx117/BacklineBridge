<?php

declare(strict_types=1);

namespace App\Controller\Finance\Entry;

use App\Dto\Finance\CancelEntryInput;
use App\Entity\TreasuryEntry;
use App\Entity\User;
use App\Form\Finance\CancelEntryType;
use App\Security\Voter\BandVoter;
use App\Service\Finance\TreasuryManager;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/tresorerie/mouvements/{id}/annuler',
    name: 'app_treasury_entry_cancel',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class CancelAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] TreasuryEntry $entry,
        Request $request,
        #[CurrentUser] User $user,
        TreasuryManager $manager,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $entry->getBand());
        // Initialiser le groupe avant une éventuelle fermeture ORM sur conflit.
        $entry->getBand()->getName();
        $input = new CancelEntryInput();
        $input->revision = $manager->fingerprint($entry->getBand());
        $input->entryRevision = (string) $entry->getRevision();
        $form = $this->createForm(CancelEntryType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->cancel($entry, $user, $input);
                $this->addFlash('success', 'Le mouvement a été annulé. L’historique est conservé.');

                return $this->redirectToRoute(
                    'app_treasury_index',
                    ['id' => $entry->getBand()->getId()],
                    303,
                );
            } catch (ConflictHttpException|OptimisticLockException) {
                $form->addError(new FormError('Les données ont changé. Recharge le formulaire.'));
                $status = 409;
            } catch (\DomainException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render(
            'finance/form.html.twig',
            [
                'band' => $entry->getBand(),
                'form' => $form->createView(),
                'title' => 'Annuler : '.$entry->getLabel(),
                'button' => 'Confirmer l’annulation',
                'backRoute' => 'app_treasury_index',
                'help' => 'Le mouvement restera visible comme annulé. Pour une vente, le stock sera également rétabli.',
            ],
            new Response(status: $status),
        );
    }
}
