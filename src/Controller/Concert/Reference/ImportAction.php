<?php

declare(strict_types=1);

namespace App\Controller\Concert\Reference;

use App\Dto\Concert\ReferenceImportInput;
use App\Entity\Concert;
use App\Form\ReferenceImportType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ReferenceImporter;
use App\Service\Concert\ReferenceImportPreview;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/reference',
    name: 'app_concert_reference_import',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class ImportAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        ReferenceImportPreview $previews,
        ReferenceImporter $importer,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::IMPORT_REFERENCE, $concert);
        $preview = $previews->forConcert($concert);
        $input = new ReferenceImportInput();
        $input->fingerprint = $preview['fingerprint'];
        $form = $this->createForm(ReferenceImportType::class, $input, [
            'changed_sections' => array_keys(
                array_filter(
                    $preview['sections'],
                    static fn (array $section): bool => $section['changed'],
                ),
            ),
        ]);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $importer->import($concert, $input);
                $this->addFlash(
                    'success',
                    'Les sections choisies ont été récupérées depuis la référence. Les versions transmises aux salles sont conservées.',
                );

                return $this->redirectToRoute(
                    'app_concert_show',
                    ['id' => $concert->getId()],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
                $status = Response::HTTP_CONFLICT;
            }
        }

        return $this->render(
            'concert/reference/import.html.twig',
            [
                'concert' => $concert,
                'preview' => $preview,
                'form' => $form->createView(),
            ],
            new Response(status: $status),
        );
    }
}
