<?php

declare(strict_types=1);

namespace App\Controller\Document;

use App\Dto\Document\DocumentInput;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\User;
use App\Enum\DocumentSource;
use App\Form\Document\DocumentType;
use App\Security\Voter\BandVoter;
use App\Service\Document\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/groupes/{id}/documents/ajouter/{source}',
    name: 'app_document_create',
    requirements: ['id' => '\d+', 'source' => 'pdf|lien'],
    methods: ['GET', 'POST'],
)]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Band $band,
        DocumentSource $source,
        #[CurrentUser] User $user,
        DocumentManager $documents,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::COLLABORATE, $band);
        $input = new DocumentInput();
        $concert = null;
        $concertId = $request->query->getInt('concert');
        if ($concertId > 0) {
            $concert = $entityManager->find(Concert::class, $concertId);
            if (null === $concert || $concert->getBand()->getId() !== $band->getId()) {
                throw $this->createNotFoundException();
            }
            $input->concert = $concert;
        }
        $roadmap = null !== $concert && 'roadmap' === $request->query->getString('usage');
        if ($roadmap) {
            $input = DocumentInput::forRoadmap($concert);
        }
        $form = $this->createForm(DocumentType::class, $input, [
            'band' => $band,
            'source' => $source,
            'concert_context' => $concert,
            'roadmap' => $roadmap,
            'validation_groups' => ['Default', $source->value],
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $document = $documents->create($band, $user, $input, $source);
                $this->addFlash('success', $roadmap
                    ? 'La feuille de route est maintenant accessible à toute l’équipe.'
                    : 'Le document est maintenant partagé avec l’équipe.',
                );

                if (null !== $document->getConcert()) {
                    return $this->redirectToRoute(
                        'app_concert_show',
                        ['id' => $document->getConcert()->getId(), '_fragment' => 'concert-documents'],
                        Response::HTTP_SEE_OTHER,
                    );
                }

                return $this->redirectToRoute(
                    'app_document_index',
                    ['id' => $band->getId()],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            } catch (FileException) {
                $form->addError(new FormError('Le PDF n’a pas pu être enregistré. Réessaie dans quelques instants.'));
            }
        }

        return $this->render('document/form.html.twig', [
            'band' => $band,
            'form' => $form,
            'source' => $source,
            'concertContext' => $concert,
            'roadmap' => $roadmap,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
