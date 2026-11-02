<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Band;
use App\Entity\User;
use App\Enum\BandRole;
use App\Repository\BandMembershipRepository;
use App\Service\Finance\MusicianAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Band> */
final class BandVoter extends Voter
{
    public const TREASURY = 'BAND_TREASURY';
    public const CALENDAR = 'BAND_CALENDAR';
    public const CALENDAR_PUBLISH = 'BAND_CALENDAR_PUBLISH';
    public const VIEW = 'BAND_VIEW';
    public const MANAGE_TECHNICAL = 'BAND_MANAGE_TECHNICAL';
    public const EDIT_PATCH = 'BAND_EDIT_PATCH';
    public const DELETE_PATCH = 'BAND_DELETE_PATCH';
    public const PLAN_CONCERT = 'BAND_PLAN_CONCERT';
    public const CONDITIONS = 'BAND_CONDITIONS';
    public const COLLABORATE = 'BAND_COLLABORATE';

    public function __construct(
        private readonly BandMembershipRepository $memberships,
        private readonly MusicianAccess $musicians,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Band
            && in_array(
                $attribute,
                [
                    self::VIEW,
                    self::TREASURY,
                    self::CALENDAR,
                    self::CALENDAR_PUBLISH,
                    self::EDIT_PATCH,
                    self::DELETE_PATCH,
                    self::PLAN_CONCERT,
                    self::CONDITIONS,
                    self::MANAGE_TECHNICAL,
                    self::COLLABORATE,
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
        $user = $token->getUser();
        if (
            !($user instanceof User)
            || ($role = $this->memberships->roleFor($user, $subject)) === null
        ) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::CALENDAR => true,
            self::TREASURY, self::CALENDAR_PUBLISH => $this->musicians->allows($user, $subject),
            self::EDIT_PATCH => $role->canEdit(),
            self::DELETE_PATCH, self::MANAGE_TECHNICAL => BandRole::Manager === $role,
            self::PLAN_CONCERT, self::CONDITIONS => $role->canPlanConcerts(),
            self::COLLABORATE => BandRole::Viewer !== $role,
            default => false,
        };
    }
}
