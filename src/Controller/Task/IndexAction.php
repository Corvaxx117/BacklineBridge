<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Entity\Band;
use App\Entity\User;
use App\Enum\TaskStatus;
use App\Repository\BandTaskRepository;
use App\Security\Voter\BandVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groupes/{id}/taches', name: 'app_task_index', requirements: ['id' => '\d+'], methods: ['GET'])]
final class IndexAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Band $band,
        BandTaskRepository $tasks,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::VIEW, $band);
        $user = $this->getUser();
        assert($user instanceof User);
        $scope = $request->query->getString('scope');
        if (!in_array($scope, ['mine', 'unassigned'], true)) {
            $scope = 'all';
        }
        $search = mb_substr(trim($request->query->getString('q')), 0, 160);
        $columns = array_fill_keys(array_column(TaskStatus::cases(), 'value'), []);
        foreach ($tasks->forBoard($band, $user, $scope, $search) as $task) {
            $columns[$task->getStatus()->value][] = $task;
        }

        return $this->render('task/index.html.twig', [
            'band' => $band,
            'columns' => $columns,
            'statuses' => TaskStatus::cases(),
            'scope' => $scope,
            'search' => $search,
            'today' => new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')),
        ]);
    }
}
