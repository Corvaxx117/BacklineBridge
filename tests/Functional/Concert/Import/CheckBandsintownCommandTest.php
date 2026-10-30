<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Import;

use App\Command\CheckBandsintownCommand;
use App\Entity\Concert;
use App\Integration\Bandsintown\BandsintownConfiguration;
use App\Integration\Bandsintown\BandsintownGateway;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Integration\Bandsintown\Dto\EventsResult;
use App\Integration\Bandsintown\Exception\BandsintownUnavailable;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckBandsintownCommandTest extends DatabaseTestCase
{
    public function testSuccessfulCheckCountsDatesWithoutImportingOrExposingKey(): void
    {
        $gateway = $this->createMock(BandsintownGateway::class);
        $gateway->expects(self::once())->method('events')->willReturn(new EventsResult([
            $this->event('10001', '2026-10-29'),
            $this->event('10002', '2026-10-30'),
            $this->event('10003', '2026-11-27'),
        ], 1, new \DateTimeImmutable('2026-10-30')));
        $tester = $this->tester($gateway);

        self::assertSame(Command::SUCCESS, $tester->execute(['band' => (string) $this->bandId]));
        self::assertMatchesRegularExpression('/^\s+3\s+2\s+1\s*$/m', $tester->getDisplay());
        self::assertStringContainsString('Aucun concert enregistré', $tester->getDisplay());
        self::assertStringNotContainsString('private-test-key', $tester->getDisplay());
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testMissingKeyDoesNotCallApi(): void
    {
        $gateway = $this->createMock(BandsintownGateway::class);
        $gateway->expects(self::never())->method('events');
        $tester = $this->tester($gateway, '');

        self::assertSame(Command::INVALID, $tester->execute(['band' => (string) $this->bandId]));
        self::assertStringContainsString('trois réglages BANDSINTOWN', $tester->getDisplay());
    }

    public function testWrongBandAndMalformedIdDoNotCallApi(): void
    {
        $gateway = $this->createMock(BandsintownGateway::class);
        $gateway->expects(self::never())->method('events');
        $tester = $this->tester($gateway);

        self::assertSame(Command::INVALID, $tester->execute(['band' => '99999999']));
        self::assertSame(Command::INVALID, $tester->execute(['band' => '../1']));
    }

    public function testUnavailableApiReturnsFailureWithoutWriting(): void
    {
        $gateway = $this->createMock(BandsintownGateway::class);
        $gateway->expects(self::once())->method('events')->willThrowException(
            new BandsintownUnavailable('Bandsintown est temporairement indisponible.'),
        );
        $tester = $this->tester($gateway);

        self::assertSame(Command::FAILURE, $tester->execute(['band' => (string) $this->bandId]));
        self::assertStringContainsString('temporairement indisponible', $tester->getDisplay());
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    private function tester(
        BandsintownGateway $gateway,
        string $key = 'private-test-key',
    ): CommandTester {
        return new CommandTester(new CheckBandsintownCommand(
            $this->entityManager(),
            new BandsintownConfiguration((string) $this->bandId, '15466975', $key),
            $gateway,
            new MockClock('2026-10-30 00:30:00 Europe/Paris'),
        ));
    }

    private function event(string $id, string $date): BandsintownEvent
    {
        return new BandsintownEvent(
            $id,
            '15466975',
            $date,
            '20:00',
            'Salle test',
            'Lyon',
            'France',
            '',
            '',
            '',
            [],
            [],
        );
    }
}
