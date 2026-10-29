<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Entity\{Band, User};
use App\Security\Voter\BandVoter;
use App\Dto\Finance\TransferInput;
use App\Form\Finance\TransferType;
use App\Service\Finance\TreasuryManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[
    Route(
        '/groupes/{id}/tresorerie/transfert',
        name: 'app_treasury_transfer',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST'],
    ),
]
final class TransferAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        Request $request,
        #[CurrentUser] User $user,
        TreasuryManager $service,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $band);
        $input = new TransferInput();
        $input->revision = $service->fingerprint($band);
        $form = $this->createForm(TransferType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->transfer($band, $user, $input);
                $this->addFlash('success', 'Enregistrement effectué.');
                return $this->redirectToRoute('app_treasury_index', ['id' => $band->getId()], 303);
            } catch (ConflictHttpException | OptimisticLockException | UniqueConstraintViolationException) {
                $form->addError(
                    new FormError(
                        'Les données ont changé ou cet élément existe déjà. Recharge le formulaire avant de réessayer.',
                    ),
                );
                $status = 409;
            } catch (\DomainException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }
        return $this->render(
            'finance/form.html.twig',
            [
                'band' => $band,
                'form' => $form->createView(),
                'title' => 'Transférer entre les comptes',
                'button' => 'Enregistrer le transfert',
                'backRoute' => 'app_treasury_index',
            ],
            new Response(status: $status),
        );
    }
}
