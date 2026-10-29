<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Repository\TechnicalSheetRepository;
use App\Service\Concert\ConcertCreator;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\DriverException;

final class ConcertCreationTest extends DatabaseTestCase
{
    public function testTheCopyPreservesAllFieldsWithNewIdentitiesAndStaysIndependentInBothDirections(): void
    {
        $reference = $this->sheet();
        $creator = static::getContainer()->get(ConcertCreator::class);
        $first = $creator->create(
            $reference,
            new \DateTimeImmutable('2026-11-14'),
            'Première salle',
            'Marseille',
        );
        $second = $creator->create(
            $reference,
            new \DateTimeImmutable('2026-12-18'),
            'Autre salle',
            'Lyon',
        );
        $firstId = $first->getId();
        $secondId = $second->getId();
        $em = $this->entityManager();
        $em->clear();

        $first = $em->find(Concert::class, $firstId);
        $sheet = $first->getPatchSheet();
        self::assertSame($this->fixture['bandName'], $sheet->getBandName());
        self::assertSame($this->fixture['documentLabel'], $sheet->getDocumentLabel());
        self::assertSame($this->fixture['notices'], $sheet->getNotices());
        self::assertCount(22, $sheet->getLines());
        self::assertSame($first, $sheet->getConcert());
        $referenceLines = $this->sheet()->getLines()->toArray();

        foreach ($sheet->getLines()->getValues() as $index => $line) {
            $expected = $this->fixture['lines'][$index];
            self::assertNotSame($referenceLines[$index]->getId(), $line->getId());
            self::assertSame($expected['channelNumber'], $line->getChannelNumber());
            self::assertSame($expected['sourceName'], $line->getSourceName());
            self::assertSame($expected['preferredMicrophone'], $line->getPreferredMicrophone());
            self::assertSame($expected['stand'], $line->getStand());
            self::assertSame($expected['notes'], $line->getNotes());
            self::assertSame($expected['section'], $line->getSection());
        }

        $concertLine = $em
            ->getRepository(PatchLine::class)
            ->findOneBy(['sheet' => $sheet, 'channelNumber' => 17]);
        $concertLine->setPreferredMicrophone('SM58 SALLE')->setChannelNumber(23);
        $reference = $this->sheet();
        $reference
            ->setDocumentLabel('Référence suivante')
            ->setNotices(['Nouvelle note de référence']);
        $this->lineForChannel(18)->setStand('AUTRE PIED');
        $em->flush();
        $em->clear();

        self::assertSame('BETA57A', $this->lineForChannel(17)->getPreferredMicrophone());
        self::assertSame('Référence suivante', $this->sheet()->getDocumentLabel());
        foreach ([$firstId, $secondId] as $id) {
            $copy = $em->find(Concert::class, $id)->getPatchSheet();
            self::assertSame($this->fixture['documentLabel'], $copy->getDocumentLabel());
            self::assertSame($this->fixture['notices'], $copy->getNotices());
            self::assertSame(
                $this->fixture['lines'][17]['stand'],
                $em
                    ->getRepository(PatchLine::class)
                    ->findOneBy(['sheet' => $copy, 'channelNumber' => 18])
                    ->getStand(),
            );
        }
        $otherSheet = $em->find(Concert::class, $secondId)->getPatchSheet();
        self::assertSame(
            'BETA57A',
            $em
                ->getRepository(PatchLine::class)
                ->findOneBy(['sheet' => $otherSheet, 'channelNumber' => 17])
                ->getPreferredMicrophone(),
        );
        self::assertSame(
            $this->sheetId,
            static::getContainer()->get(TechnicalSheetRepository::class)->findReference()?->getId(),
        );
    }

    public function testAnSqlFailureRollsBackTheEntireConcertAndItsCopy(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement(
            "CREATE TRIGGER reject_test_copy BEFORE INSERT ON patch_line BEGIN SELECT RAISE(ABORT, 'Forced copy failure'); END",
        );

        try {
            static::getContainer()
                ->get(ConcertCreator::class)
                ->create(
                    $this->sheet(),
                    new \DateTimeImmutable('2026-11-14'),
                    'Salle de test',
                    'Marseille',
                );
            self::fail('La copie aurait dû échouer.');
        } catch (DriverException) {
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM concert'));
            self::assertSame(
                1,
                (int) $connection->fetchOne('SELECT COUNT(*) FROM technical_sheet'),
            );
            self::assertSame(22, (int) $connection->fetchOne('SELECT COUNT(*) FROM patch_line'));
        }
    }

    public function testAConcertCopyCannotBeUsedAsTheGroupReference(): void
    {
        $creator = static::getContainer()->get(ConcertCreator::class);
        $concert = $creator->create(
            $this->sheet(),
            new \DateTimeImmutable('2026-11-14'),
            'Salle de test',
            'Marseille',
        );

        $this->expectException(\InvalidArgumentException::class);
        $creator->create(
            $concert->getPatchSheet(),
            new \DateTimeImmutable('2026-11-15'),
            'Autre salle',
            'Lyon',
        );
    }
}
