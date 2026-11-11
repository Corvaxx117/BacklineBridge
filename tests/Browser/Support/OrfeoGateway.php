<?php

declare(strict_types=1);

namespace App\Tests\Browser\Support;

use App\Integration\Orfeo\Dto\EventsResult;

final class OrfeoGateway implements \App\Integration\Orfeo\OrfeoGateway
{
    public function events(): EventsResult
    {
        return new EventsResult([], 0, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }
}
