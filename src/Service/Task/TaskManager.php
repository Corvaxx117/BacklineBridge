<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Dto\Task\TaskInput;
use App\Entity\Band;
use App\Entity\BandTask;
use App\Entity\User;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class TaskManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function create(Band $band, User $author, TaskInput $input): BandTask
    {
        $task = new BandTask($band, $author);
        $this->apply($task, $input);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    public function update(BandTask $task, TaskInput $input): void
    {
        $this->checkRevision($task, $input->revision);
        $this->apply($task, $input);
        $this->entityManager->flush();
    }

    public function changeStatus(BandTask $task, TaskStatus $status, string $revision): void
    {
        $this->checkRevision($task, $revision);
        $task->changeStatus($status);
        $this->entityManager->flush();
    }

    public function remove(BandTask $task, string $revision): void
    {
        $this->checkRevision($task, $revision);
        // Guard the DELETE itself, including a change arriving after the revision check.
        $affected = $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM band_task WHERE id = :id AND revision = :revision',
            ['id' => $task->getId(), 'revision' => $task->getRevision()],
        );
        if (1 !== $affected) {
            throw new ConflictHttpException('Cette tâche a changé. Recharge le tableau.');
        }
        $this->entityManager->detach($task);
    }

    private function apply(BandTask $task, TaskInput $input): void
    {
        assert(null !== $input->status);
        $task->update($input->title, $input->description, $input->assignee, $input->dueAt, $input->status);
    }

    private function checkRevision(BandTask $task, string $revision): void
    {
        if ($revision !== (string) $task->getRevision()) {
            throw new ConflictHttpException('Cette tâche a changé. Recharge le tableau.');
        }
    }
}
