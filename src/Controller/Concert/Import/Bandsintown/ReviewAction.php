<?php

declare(strict_types=1);

namespace App\Controller\Concert\Import\Bandsintown;

use App\Dto\Concert\BandsintownImportInput;
use App\Entity\{Band, User};
use App\Form\Concert\BandsintownImportType;
use App\Security\Voter\BandVoter;
use App\Service\Concert\Import\Bandsintown\{ConcertImporter, ImportPreview, PreviewStore};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[
    Route(
        '/groupes/{id}/concerts/import-bandsintown/{token}',
        name: 'app_concert_bandsintown_review',
        requirements: ['id' => '\d+', 'token' => '[a-f0-9]{32}'],
        methods: ['GET', 'POST'],
    ),
]
final class ReviewAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        string $token,
        #[CurrentUser] User $user,
        Request $request,
        PreviewStore $previews,
        ImportPreview $preview,
        ConcertImporter $importer,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::PLAN_CONCERT, $band);
        $result = $previews->load($token, $band, $user);
        $rows = $preview->rows($band, $result->events);
        $choices = [];
        foreach ($rows as $id => $row) {
            if (in_array($row['state'], ['new', 'link'], true)) {
                $event = $row['event'];
                $choices[
                    $event->date . ' · ' . $event->venue . ' · ' . $event->city . ' (#' . $id . ')'
                ] = (string) $id;
            }
        }
        $form = $this->createForm(BandsintownImportType::class, new BandsintownImportInput(), [
            'event_choices' => $choices,
            'csrf_token_id' => 'bandsintown_import_' . $token,
        ])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imported = $importer->import($band, $user, $result->events, $form->getData());
                $previews->remove($token);
                $this->addFlash(
                    'success',
                    sprintf(
                        'Import terminé : %d concert(s) créé(s), %d annonce(s) rattachée(s), %d doublon(s) ignoré(s).',
                        $imported->created,
                        $imported->linked,
                        $imported->skipped,
                    ),
                );
                return $this->redirectToRoute('app_concert_index', [], 303);
            } catch (UniqueConstraintViolationException | OptimisticLockException) {
                $form->addError(
                    new FormError(
                        'Les concerts ont changé pendant l’import. Aucun nouvel import n’a été enregistré. Recharge l’aperçu.',
                    ),
                );
                $status = 409;
            } catch (\DomainException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }
        return $this->render(
            'concert/import/bandsintown/review.html.twig',
            [
                'band' => $band,
                'rows' => $rows,
                'result' => $result,
                'form' => $form->createView(),
                'hasChoices' => $choices !== [],
            ],
            new Response(status: $status),
        );
    }
}
