<?php

declare(strict_types=1);

namespace App\Dto\Venue;

use Symfony\Component\Validator\Constraints as Assert;

final class InvitationInput
{
    #[Assert\NotBlank, Assert\Length(max: 150)]
    public string $label = '';
    #[Assert\NotNull, Assert\Choice(choices: [7, 30, 90])]
    public ?int $days = 30;
}
