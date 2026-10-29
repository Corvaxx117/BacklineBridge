<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class PatchLinePersistenceTest extends DatabaseTestCase
{
    public function testTheDatabaseRejectsDuplicateChannelsEvenWithoutFormValidation(): void
    {
        $duplicate = new PatchLine()
            ->setSheet($this->sheet())
            ->setChannelNumber(17)
            ->setSourceName('DUPLICATE WITHOUT VALIDATOR')
            ->setSection('vocals');
        $em = $this->entityManager();
        $em->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    public function testTwoDifferentSheetsCanBothHaveAChannelSeventeen(): void
    {
        $otherSheet = new TechnicalSheet()
            ->setBandName('AUTRE GROUPE')
            ->setDocumentLabel('Test')
            ->setNotices([]);
        $otherSheet->addLine(
            new PatchLine()
                ->setChannelNumber(17)
                ->setSourceName('AUTRE VOIX')
                ->setSection('vocals'),
        );
        $em = $this->entityManager();
        $em->persist($otherSheet);
        $em->flush();
        self::assertNotNull($otherSheet->getId());
        $otherId = $otherSheet->getId();
        $em->clear();

        $lines = $em->getRepository(PatchLine::class)->findBy(['channelNumber' => 17]);
        self::assertCount(2, $lines);
        $sheetIds = array_map(
            static fn(PatchLine $line): ?int => $line->getSheet()?->getId(),
            $lines,
        );
        self::assertContains($this->sheetId, $sheetIds);
        self::assertContains($otherId, $sheetIds);
    }
}
