<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Dto\Task\TaskInput;
use App\Entity\BandTask;
use App\Form\Task\TaskType;
use App\Security\Voter\TaskVoter;
use App\Service\Task\TaskManager;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/taches/{id}/modifier', name: 'app_task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] BandTask $task,
        TaskManager $tasks,
    ): Response {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);
        $input = TaskInput::fromTask($task);
        $form = $this->createForm(TaskType::class, $input, ['band' => $task->getBand()])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $tasks->update($task, $input);
                $this->addFlash('success', 'La tâche a été mise à jour pour le groupe.');

                return $this->redirectToRoute('app_task_index', ['id' => $task->getBand()->getId()], 303);
            } catch (ConflictHttpException|OptimisticLockException) {
                $form->addError(new FormError(
                    'Cette tâche a changé. Tes valeurs restent affichées : conserve-les puis recharge la page.',
                ));
                $status = 409;
            }
        }

        return $this->render('task/form.html.twig', [
            'band' => $task->getBand(),
            'form' => $form,
            'task' => $task,
        ], new Response(status: $status));
    }
}
