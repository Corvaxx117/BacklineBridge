<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\BandDocument;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, BandDocument> */
final class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';
    public const DELETE = 'DOCUMENT_DELETE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof BandDocument && in_array($attribute, [self::VIEW, self::DELETE], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        if (!$this->decisions->decide($token, [BandVoter::VIEW], $subject->getBand())) {
            return false;
        }
        if (self::VIEW === $attribute) {
            return true;
        }
        if ($this->decisions->decide($token, [BandVoter::MANAGE_TECHNICAL], $subject->getBand())) {
            return true;
        }
        $user = $token->getUser();

        return $user instanceof User
            && $subject->getCreatedBy()?->getId() === $user->getId()
            && $this->decisions->decide($token, [BandVoter::COLLABORATE], $subject->getBand());
    }
}
