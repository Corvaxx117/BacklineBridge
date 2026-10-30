<?php

declare(strict_types=1);

namespace App\Controller\Finance\Note;

use App\Entity\Band;
use App\Entity\FinanceNote;
use App\Entity\User;
use App\Security\Voter\BandVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/groupes/{id}/tresorerie/notes',
    name: 'app_treasury_note_new',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
),]
final class CreateAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $band);
        if (
            !$this->isCsrfTokenValid(
                'finance_note_'.$band->getId(),
                $request->request->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException();
        }
        $text = trim($request->request->getString('text'));
        if ('' === $text || mb_strlen($text) > 2000) {
            throw new BadRequestHttpException('La note doit contenir entre 1 et 2 000 caractères.');
        }
        $em->persist(new FinanceNote($band, $text, $user));
        $em->flush();

        return $this->redirectToRoute('app_treasury_index', ['id' => $band->getId()], 303);
    }
}
