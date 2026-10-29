<?php

declare(strict_types=1);

namespace App\Dto\Member;

use Symfony\Component\Validator\Constraints as Assert;

final class ActivationInput
{
    #[
        Assert\NotBlank(message: 'Renseigne ton code d’activation personnel.'),
        Assert\Length(max: 100),
    ]
    public string $code = '';
    #[
        Assert\NotBlank(message: 'Choisis et confirme ton mot de passe.'),
        Assert\Length(min: 12, max: 128),
        Assert\PasswordStrength(
            minScore: Assert\PasswordStrength::STRENGTH_STRONG,
            message: 'Choisis un mot de passe plus robuste, par exemple une longue phrase de passe.',
        ),
    ]
    public string $password = '';
}
