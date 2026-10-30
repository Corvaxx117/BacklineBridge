<?php

declare(strict_types=1);

namespace App\Service\Member;

use App\Dto\Member\ActivationInput;
use App\Entity\MemberProfile;
use App\Entity\User;
use App\Validator\AccountPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AccountActivator
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function activate(
        MemberProfile $profile,
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $password,
    ): User {
        $normalised = strtoupper(preg_replace('/[\s-]/', '', $code) ?? '');
        $user = $profile->getUser();
        $activationHash = $profile->getActivationHash();
        if (
            !$profile->canActivate()
            || null === $user
            || null === $activationHash
            || !hash_equals($activationHash, hash('sha256', $normalised))
        ) {
            throw new \DomainException('Ce code est invalide, expiré ou déjà utilisé.');
        }
        $input = new ActivationInput();
        $input->code = $code;
        $input->password = $password;
        if (count($this->validator->validate($input)) > 0) {
            throw new \DomainException(AccountPassword::HELP);
        }
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $profile->consumeActivation();
        $this->em->flush();

        return $user;
    }

    public function issue(MemberProfile $profile): string
    {
        if (null === $profile->getUser()) {
            throw new \DomainException(
                'Les coordonnées de cette personne sont encore à compléter.',
            );
        }
        $code = strtoupper(bin2hex(random_bytes(12)));
        $profile->issueActivation(hash('sha256', $code), new \DateTimeImmutable('+30 days'));

        return implode('-', str_split($code, 4));
    }
}
