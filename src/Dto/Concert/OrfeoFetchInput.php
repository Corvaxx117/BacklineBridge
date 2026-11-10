<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use Symfony\Component\Validator\Constraints as Assert;

final class OrfeoFetchInput
{
    #[Assert\Choice(choices: ['upcoming', 'past', 'all'])]
    public string $period = 'upcoming';

    #[Assert\Regex(pattern: '/^[1-9][0-9]*:[1-9][0-9]*$/D')]
    public ?string $eventId = null;
}
