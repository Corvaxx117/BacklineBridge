<?php

declare(strict_types=1);

namespace App\Integration\Bandsintown;

use App\Integration\Bandsintown\Dto\EventsResult;

interface BandsintownGateway
{
    public function events(): EventsResult;
}
