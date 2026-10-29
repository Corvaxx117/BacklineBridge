<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Band;
use App\Entity\BandMembership;
use App\Entity\User;
use App\Enum\BandRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {}

    public function create(
        string $email,
        string $name,
        #[\SensitiveParameter] string $plainPassword,
        Band $band,
        BandRole $role,
    ): User {
        $user = new User($email, $name);
        $violations = $this->validator->validate($user);
        $violations->addAll(
            $this->validator->validate($plainPassword, [
                new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                new Assert\Length(
                    min: 12,
                    max: 128,
                    minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                ),
            ]),
        );
        if (count($violations) > 0) {
            throw new ValidationFailedException($user, $violations);
        }

        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->persist(new BandMembership($band, $user, $role));
        $this->entityManager->flush();

        return $user;
    }
}
