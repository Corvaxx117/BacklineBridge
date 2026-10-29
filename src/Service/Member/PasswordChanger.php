<?php

declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\User;
use App\Validator\AccountPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PasswordChanger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {}

    public function change(User $user, #[\SensitiveParameter] string $password): void
    {
        if (count($this->validator->validate($password, new AccountPassword())) > 0) {
            throw new \DomainException(AccountPassword::HELP);
        }
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->entityManager->flush();
    }
}
