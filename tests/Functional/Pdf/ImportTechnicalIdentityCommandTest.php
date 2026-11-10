<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pdf;

use App\Command\ImportTechnicalIdentityCommand;
use App\Entity\Band;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportTechnicalIdentityCommandTest extends DatabaseTestCase
{
    public function testThePrivateFileIsImportedWithoutPrintingItsCoordinates(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'bb-rider-import-');
        self::assertIsString($file);
        try {
            file_put_contents($file, json_encode([
                'style' => 'Indie', 'description' => 'Live show',
                'contacts' => [['name' => 'Test', 'role' => 'FOH', 'email' => 'sound@example.test', 'phone' => '0100000000']],
            ], JSON_THROW_ON_ERROR));
            $command = new CommandTester(static::getContainer()->get(ImportTechnicalIdentityCommand::class));
            self::assertSame(Command::SUCCESS, $command->execute(['file' => $file, 'band' => (string) $this->bandId]));
            self::assertStringNotContainsString('sound@example.test', $command->getDisplay());
            $this->entityManager()->clear();
            self::assertSame('Indie', $this->entityManager()->find(Band::class, $this->bandId)->getTechnicalIdentity()->style);
            file_put_contents($file, '{"socialSecurityNumber":"DO_NOT_PUBLISH"}');
            self::assertSame(Command::INVALID, $command->execute(['file' => $file, 'band' => (string) $this->bandId]));
            self::assertSame('Indie', $this->entityManager()->find(Band::class, $this->bandId)->getTechnicalIdentity()->style);
        } finally {
            unlink($file);
        }
    }
}
