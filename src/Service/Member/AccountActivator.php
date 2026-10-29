<?php

declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\MemberProfile;
use App\Dto\Member\ActivationInput;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AccountActivator
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {}

    public function activate(
        MemberProfile $profile,
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $password,
    ): void {
        $normalised = strtoupper(preg_replace('/[\s-]/', '', $code));
        if (
            !$profile->canActivate() ||
            !hash_equals($profile->getActivationHash(), hash('sha256', $normalised))
        ) {
            throw new \DomainException('Ce code est invalide, expiré ou déjà utilisé.');
        }
        $input = new ActivationInput();
        $input->code = $code;
        $input->password = $password;
        if (count($this->validator->validate($input)) > 0) {
            throw new \DomainException(
                'Choisis une phrase de passe robuste entre 12 et 128 caractères.',
            );
        }
        $profile
            ->getUser()
            ->setPassword($this->hasher->hashPassword($profile->getUser(), $password));
        $profile->consumeActivation();
        $this->em->flush();
    }

    public function issue(MemberProfile $profile): string
    {
        if ($profile->getUser() === null) {
            throw new \DomainException(
                'Les coordonnées de cette personne sont encore à compléter.',
            );
        }
        $code = strtoupper(bin2hex(random_bytes(12)));
        $profile->issueActivation(hash('sha256', $code), new \DateTimeImmutable('+30 days'));
        return implode('-', str_split($code, 4));
    }
}
