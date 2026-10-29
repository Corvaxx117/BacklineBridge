<?php

declare(strict_types=1);

namespace App\Dto\Venue;

use App\Entity\VenueInvitation;

final readonly class IssuedInvitation
{
    public function __construct(public VenueInvitation $invitation, public string $token) {}
}
