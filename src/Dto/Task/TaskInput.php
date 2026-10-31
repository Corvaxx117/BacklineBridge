<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Entity\BandTask;
use App\Entity\MemberProfile;
use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskInput
{
    #[Assert\NotBlank(message: 'Donne un titre à cette tâche.')]
    #[Assert\Length(max: 180)]
    public string $title = '';

    #[Assert\Length(max: 3000)]
    public ?string $description = null;

    public ?MemberProfile $assignee = null;
    public ?\DateTimeImmutable $dueAt = null;

    #[Assert\NotNull(message: 'Choisis un état.')]
    public ?TaskStatus $status = TaskStatus::Todo;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,18}$/D')]
    public string $revision = '1';

    public static function fromTask(BandTask $task): self
    {
        $input = new self();
        $input->title = $task->getTitle();
        $input->description = $task->getDescription();
        $input->assignee = $task->getAssignee();
        $input->dueAt = $task->getDueAt();
        $input->status = $task->getStatus();
        $input->revision = (string) $task->getRevision();

        return $input;
    }
}
