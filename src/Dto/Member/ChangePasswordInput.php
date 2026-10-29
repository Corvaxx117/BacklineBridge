<?php

declare(strict_types=1);

namespace App\Dto\Member;

use App\Validator\AccountPassword;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordInput
{
    #[Assert\NotBlank(message: 'Renseigne ton mot de passe actuel.')]
    #[UserPassword(message: 'Le mot de passe actuel est incorrect.')]
    public string $currentPassword = '';

    #[AccountPassword]
    public string $password = '';
}
