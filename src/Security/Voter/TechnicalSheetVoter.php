<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\TechnicalSheet;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/** @extends Voter<string, TechnicalSheet> */
final class TechnicalSheetVoter extends Voter
{
    public const VIEW = 'SHEET_VIEW';
    public const MANAGE = 'SHEET_MANAGE';
    public const EDIT = 'SHEET_EDIT';
    public const DELETE = 'SHEET_DELETE';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof TechnicalSheet &&
            in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::MANAGE], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $band = $subject->getBand();
        if ($band === null) {
            return false;
        }

        return $this->decisions->decide(
            $token,
            [
                match ($attribute) {
                    self::VIEW => BandVoter::VIEW,
                    self::EDIT => BandVoter::EDIT_PATCH,
                    self::DELETE => BandVoter::DELETE_PATCH,
                    self::MANAGE => BandVoter::MANAGE_TECHNICAL,
                },
            ],
            $band,
        );
    }
}
