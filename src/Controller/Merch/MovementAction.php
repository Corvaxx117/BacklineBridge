<?php

declare(strict_types=1);

namespace App\Controller\Merch;

use App\Dto\Merch\StockMovementInput;
use App\Entity\MerchVariant;
use App\Entity\User;
use App\Enum\StockMovementKind;
use App\Form\Merch\StockMovementType;
use App\Security\Voter\BandVoter;
use App\Service\Finance\TreasuryManager;
use App\Service\Merch\StockManager;
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
    '/stock/variantes/{id}/mouvement',
    name: 'app_stock_movement',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class MovementAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] MerchVariant $variant,
        Request $request,
        #[CurrentUser] User $user,
        StockManager $stock,
        TreasuryManager $treasury,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $variant->getBand());
        // Initialiser le groupe avant une éventuelle fermeture ORM sur conflit.
        $variant->getBand()->getName();
        $input = new StockMovementInput();
        $input->revision = (string) $variant->getRevision();
        $input->treasuryRevision = $treasury->fingerprint($variant->getBand());
        if (null === $variant->getQuantity() || 'count' === $request->query->get('action')) {
            $input->kind = StockMovementKind::Count;
            $input->quantity = $variant->getQuantity();
        }
        $form = $this->createForm(StockMovementType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $stock->record($variant, $user, $input);
                $this->addFlash(
                    'success',
                    'Le stock a été mis à jour'.
                        (StockMovementKind::Sale === $input->kind
                            ? ' et la recette a été ajoutée.'
                            : '.'),
                );

                return $this->redirectToRoute(
                    'app_stock_index',
                    ['id' => $variant->getBand()->getId()],
                    303,
                );
            } catch (ConflictHttpException|OptimisticLockException) {
                $form->addError(
                    new FormError(
                        'Le stock ou les soldes ont changé. Recharge le formulaire avant de réessayer.',
                    ),
                );
                $status = 409;
            } catch (\DomainException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render(
            'merch/movement.html.twig',
            ['band' => $variant->getBand(), 'variant' => $variant, 'form' => $form->createView()],
            new Response(status: $status),
        );
    }
}
