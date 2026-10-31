<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\BandTask;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, BandTask> */
final class TaskVoter extends Voter
{
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof BandTask && in_array($attribute, [self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        if (!$this->decisions->decide($token, [BandVoter::COLLABORATE], $subject->getBand())) {
            return false;
        }
        if (self::EDIT === $attribute) {
            return true;
        }
        $user = $token->getUser();

        return $this->decisions->decide($token, [BandVoter::MANAGE_TECHNICAL], $subject->getBand())
            || ($user instanceof User && $subject->getCreatedBy()?->getId() === $user->getId());
    }
}
