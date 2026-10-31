<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Dto\Task\TaskStateInput;
use App\Entity\BandTask;
use App\Form\Task\TaskStateType;
use App\Security\Voter\TaskVoter;
use App\Service\Task\TaskManager;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/taches/{id}/etat', name: 'app_task_state', requirements: ['id' => '\d+'], methods: ['POST'])]
final class StateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] BandTask $task,
        TaskManager $tasks,
    ): Response {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);
        $input = new TaskStateInput();
        $form = $this->createForm(TaskStateType::class, $input, [
            'csrf_token_id' => 'task_state_'.$task->getId(),
        ])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('task/problem.html.twig', [
                'band' => $task->getBand(),
                'message' => 'Le formulaire est incomplet ou a expiré. Recharge le tableau pour réessayer.',
            ], new Response(status: 422));
        }
        assert(null !== $input->status);
        try {
            $tasks->changeStatus($task, $input->status, $input->revision);
        } catch (ConflictHttpException|OptimisticLockException) {
            return $this->render('task/problem.html.twig', [
                'band' => $task->getBand(),
                'message' => 'Cette tâche a été modifiée entre-temps. Recharge le tableau avant de réessayer.',
            ], new Response(status: 409));
        }
        $this->addFlash('success', 'État de la tâche : '.$input->status->label().'.');

        return $this->redirectToRoute('app_task_index', ['id' => $task->getBand()->getId()], 303);
    }
}
