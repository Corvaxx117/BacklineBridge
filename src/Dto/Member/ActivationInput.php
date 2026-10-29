<?php

declare(strict_types=1);

namespace App\Dto\Member;

use Symfony\Component\Validator\Constraints as Assert;

final class ActivationInput
{
    #[Assert\NotBlank, Assert\Length(max: 100)]
    public string $code = '';
    #[
        Assert\NotBlank,
        Assert\Length(min: 12, max: 128),
        Assert\PasswordStrength(
            minScore: Assert\PasswordStrength::STRENGTH_STRONG,
            message: 'Choisis un mot de passe plus robuste, par exemple une longue phrase de passe.',
        ),
    ]
    public string $password = '';
}
