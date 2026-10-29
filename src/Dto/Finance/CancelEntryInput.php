<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use Symfony\Component\Validator\Constraints as Assert;

final class CancelEntryInput
{
    #[Assert\NotBlank, Assert\Length(max: 250)]
    public string $reason = '';
    #[Assert\NotBlank]
    public string $revision = '';
    #[Assert\NotBlank]
    public string $entryRevision = '';
}
