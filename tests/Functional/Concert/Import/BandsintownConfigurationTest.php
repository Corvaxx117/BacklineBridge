<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Import;

use App\Integration\Bandsintown\BandsintownGateway;
use App\Tests\DatabaseTestCase;

final class BandsintownConfigurationTest extends DatabaseTestCase
{
    public function testMissingConfigurationShowsInstructionsWithoutCallingBandsintown(): void
    {
        $gateway = $this->createMock(BandsintownGateway::class);
        $gateway->expects(self::never())->method('events');
        static::getContainer()->set(BandsintownGateway::class, $gateway);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/concerts/import-bandsintown');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Relier le compte du groupe');
        self::assertSelectorNotExists('form[name="bandsintown_fetch"]');
    }
}
