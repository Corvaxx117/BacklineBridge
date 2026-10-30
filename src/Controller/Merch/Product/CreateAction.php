<?php

declare(strict_types=1);

namespace App\Controller\Merch\Product;

use App\Dto\Merch\ProductInput;
use App\Entity\Band;
use App\Entity\User;
use App\Form\Merch\ProductType;
use App\Security\Voter\BandVoter;
use App\Service\Merch\ProductCreator;
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

#[Route(
    '/groupes/{id}/stock/nouveau',
    name: 'app_stock_product_new',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class CreateAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        Request $request,
        #[CurrentUser] User $user,
        ProductCreator $service,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $band);
        $input = new ProductInput();

        $form = $this->createForm(ProductType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->create($band, $user, $input);
                $this->addFlash('success', 'Enregistrement effectué.');

                return $this->redirectToRoute('app_stock_index', ['id' => $band->getId()], 303);
            } catch (ConflictHttpException|OptimisticLockException|UniqueConstraintViolationException) {
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
                'title' => 'Ajouter un produit',
                'button' => 'Créer le produit',
                'backRoute' => 'app_stock_index',
            ],
            new Response(status: $status),
        );
    }
}
