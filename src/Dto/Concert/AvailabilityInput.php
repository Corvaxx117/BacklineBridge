<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Enum\AvailabilityStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class AvailabilityInput
{
    #[Assert\NotNull(message: 'Choisis ta disponibilité.')]
    public ?AvailabilityStatus $status = AvailabilityStatus::Pending;

    #[
        Assert\Length(
            max: 500,
            maxMessage: 'La précision ne peut pas dépasser {{ limit }} caractères.',
        ),
    ]
    public ?string $comment = null;

    #[
        Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'Recharge le concert avant de répondre.',
        ),
    ]
    #[Assert\NotBlank]
    public string $expectedDate = '';
}
