<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use Symfony\Component\Validator\Constraints as Assert;

final class BandsintownFetchInput
{
    #[Assert\Choice(choices: ['all', 'upcoming', 'past'])]
    public string $period = 'all';
}
