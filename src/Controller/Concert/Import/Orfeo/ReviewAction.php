<?php

declare(strict_types=1);

namespace App\Controller\Concert\Import\Orfeo;

use App\Dto\Concert\OrfeoImportInput;
use App\Entity\Band;
use App\Entity\User;
use App\Form\Concert\OrfeoImportType;
use App\Security\Voter\BandVoter;
use App\Service\Concert\Import\Orfeo\ConcertImporter;
use App\Service\Concert\Import\Orfeo\ImportPreview;
use App\Service\Concert\Import\Orfeo\PreviewStore;
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

#[Route('/groupes/{id}/concerts/import-orfeo/{token}', name: 'app_concert_orfeo_review', requirements: ['id' => '\d+', 'token' => '[a-f0-9]{32}'], methods: ['GET', 'POST'])]
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
        $snapshot = $previews->load($token, $band, $user);
        $result = $snapshot['result'];
        $rows = $preview->rows($band, $result->events);
        $eventChoices = $targetKeys = [];
        $concertChoices = ['Créer un nouveau concert' => 'new'];
        foreach ($preview->concerts($band) as $concert) {
            $concertChoices[$concert['date'].' · '.$concert['venue'].' · '.$concert['city'].' (#'.$concert['id'].')'] = (string) $concert['id'];
        }
        $input = new OrfeoImportInput();
        foreach ($rows as $id => $row) {
            if (in_array($row['state'], ['cancelled', 'occupied'], true)) {
                continue;
            }
            $event = $row['event'];
            $eventChoices[$event->date.' · '.$event->venue.' (#'.$id.')'] = $id;
            if ('imported' !== $row['state']) {
                $key = OrfeoImportInput::fieldKey($id);
                $targetKeys[] = $key;
                $input->targets[$key] = $row['target'];
            }
        }
        $form = $this->createForm(OrfeoImportType::class, $input, ['event_choices' => $eventChoices,
            'target_keys' => $targetKeys, 'concert_choices' => $concertChoices,
            'csrf_token_id' => 'orfeo_import_'.$token])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imported = $importer->import($band, $user, $result->events, $form->getData(), $snapshot['versions'], $snapshot['sourceVersions']);
                $previews->remove($token);
                $this->addFlash('success', sprintf('Orfeo : %d date(s) créée(s), %d rattachée(s), %d actualisée(s), %d contact(s) ajouté(s). %d contact(s) BB conservé(s).',
                    $imported->created, $imported->linked, $imported->updated, $imported->contactsAdded, $imported->contactsPreserved));

                return $this->redirectToRoute('app_concert_index', [], 303);
            } catch (UniqueConstraintViolationException|OptimisticLockException|ConflictHttpException) {
                $form->addError(new FormError('Les concerts ou leurs liens Orfeo ont changé. Rien n’a été importé. Recharge l’aperçu.'));
                $status = 409;
            } catch (\DomainException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('concert/import/orfeo/review.html.twig', ['band' => $band, 'rows' => $rows,
            'result' => $result, 'form' => $form->createView(), 'hasChoices' => [] !== $eventChoices], new Response(status: $status));
    }
}
