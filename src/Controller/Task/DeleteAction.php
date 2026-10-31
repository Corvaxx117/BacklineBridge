<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Entity\BandTask;
use App\Security\Voter\TaskVoter;
use App\Service\Task\TaskManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/taches/{id}/supprimer', name: 'app_task_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class DeleteAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] BandTask $task,
        TaskManager $tasks,
    ): Response {
        $this->denyAccessUnlessGranted(TaskVoter::DELETE, $task);
        $band = $task->getBand();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('task_delete_'.$task->getId(), $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Le formulaire a expiré.');
            }
            try {
                $tasks->remove($task, $request->getPayload()->getString('revision'));
            } catch (ConflictHttpException) {
                return $this->render('task/problem.html.twig', [
                    'band' => $band,
                    'message' => 'Cette tâche a changé depuis ta confirmation. Consulte-la avant de la supprimer.',
                ], new Response(status: 409));
            }
            $this->addFlash('success', 'La tâche a été supprimée.');

            return $this->redirectToRoute('app_task_index', ['id' => $band->getId()], 303);
        }

        return $this->render('task/delete.html.twig', ['band' => $band, 'task' => $task]);
    }
}
