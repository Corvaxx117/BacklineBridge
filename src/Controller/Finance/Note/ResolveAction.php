<?php

declare(strict_types=1);

namespace App\Controller\Finance\Note;

use App\Entity\FinanceNote;
use App\Entity\User;
use App\Security\Voter\BandVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/tresorerie/notes/{id}/verifier',
    name: 'app_treasury_note_resolve',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
),]
final class ResolveAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] FinanceNote $note,
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $note->getBand());
        if (
            !$this->isCsrfTokenValid(
                'finance_note_resolve_'.$note->getId(),
                $request->request->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException();
        }
        if (null === $note->getResolvedAt()) {
            $note->resolve($user);
            $em->flush();
        }

        return $this->redirectToRoute(
            'app_treasury_index',
            ['id' => $note->getBand()->getId()],
            303,
        );
    }
}
