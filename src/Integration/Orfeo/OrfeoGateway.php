<?php

declare(strict_types=1);

namespace App\Integration\Orfeo;

use App\Integration\Orfeo\Dto\EventsResult;

interface OrfeoGateway
{
    public function events(): EventsResult;
}
