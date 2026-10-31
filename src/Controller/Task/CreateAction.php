<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Dto\Task\TaskInput;
use App\Entity\Band;
use App\Entity\User;
use App\Form\Task\TaskType;
use App\Security\Voter\BandVoter;
use App\Service\Task\TaskManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/groupes/{id}/taches/ajouter',
    name: 'app_task_create',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
)]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Band $band,
        TaskManager $tasks,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::COLLABORATE, $band);
        $user = $this->getUser();
        assert($user instanceof User);
        $input = new TaskInput();
        $form = $this->createForm(TaskType::class, $input, ['band' => $band])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $tasks->create($band, $user, $input);
            $this->addFlash('success', 'La tâche a été ajoutée au tableau du groupe.');

            return $this->redirectToRoute('app_task_index', ['id' => $band->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/form.html.twig', [
            'band' => $band,
            'form' => $form,
            'task' => null,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
