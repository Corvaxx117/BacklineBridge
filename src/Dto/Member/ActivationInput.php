<?php

declare(strict_types=1);

namespace App\Dto\Member;

use App\Validator\AccountPassword;
use Symfony\Component\Validator\Constraints as Assert;

final class ActivationInput
{
    #[
        Assert\NotBlank(message: 'Renseigne ton code d’activation personnel.'),
        Assert\Length(max: 100),
    ]
    public string $code = '';
    #[AccountPassword]
    public string $password = '';
}
