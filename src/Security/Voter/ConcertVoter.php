<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Concert;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Concert> */
final class ConcertVoter extends Voter
{
    public const VIEW = 'CONCERT_VIEW';
    public const SHARE = 'CONCERT_SHARE';
    public const EDIT = 'CONCERT_EDIT';
    public const UPDATE = 'CONCERT_UPDATE';
    public const CONDITIONS = 'CONCERT_CONDITIONS';
    public const RESPOND_AVAILABILITY = 'CONCERT_RESPOND_AVAILABILITY';
    public const IMPORT_REFERENCE = 'CONCERT_IMPORT_REFERENCE';
    public const DELETE = 'CONCERT_DELETE';
    public const CONTACTS = 'CONCERT_CONTACTS';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Concert
            && in_array(
                $attribute,
                [
                    self::VIEW,
                    self::EDIT,
                    self::UPDATE,
                    self::CONDITIONS,
                    self::SHARE,
                    self::RESPOND_AVAILABILITY,
                    self::IMPORT_REFERENCE,
                    self::DELETE,
                    self::CONTACTS,
                ],
                true,
            );
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $bandAttribute = match ($attribute) {
            self::VIEW => BandVoter::VIEW,
            self::EDIT => BandVoter::EDIT_PATCH,
            self::UPDATE => BandVoter::PLAN_CONCERT,
            self::CONDITIONS => BandVoter::CONDITIONS,
            self::CONTACTS => BandVoter::COLLABORATE,
            self::DELETE => BandVoter::MANAGE_TECHNICAL,
            self::SHARE,
            self::RESPOND_AVAILABILITY,
            self::IMPORT_REFERENCE => BandVoter::MANAGE_TECHNICAL,
            default => null,
        };

        return null !== $bandAttribute
            && $this->decisions->decide($token, [$bandAttribute], $subject->getBand());
    }
}
