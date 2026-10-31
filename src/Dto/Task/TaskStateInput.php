<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskStateInput
{
    #[Assert\NotNull]
    public ?TaskStatus $status = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,18}$/D')]
    public string $revision = '';
}
