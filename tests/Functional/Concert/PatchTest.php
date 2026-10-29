<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Service\Concert\ConcertCreator;
use App\Tests\DatabaseTestCase;

final class PatchTest extends DatabaseTestCase
{
    public function testEditingAConcertSourceReturnsToThatConcertAndPreservesTheReference(): void
    {
        $concert = $this->createConcert();
        $concertId = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $line = $this->entityManager()
            ->getRepository(PatchLine::class)
            ->findOneBy(['sheet' => $sheetId, 'channelNumber' => 17]);
        self::assertInstanceOf(PatchLine::class, $line);
        $lineId = $line->getId();
        $this->submitLineForm('/sources/' . $lineId . '/edit', [
            'preferredMicrophone' => 'SM58 POUR CETTE DATE',
        ]);

        self::assertResponseRedirects('/concerts/' . $concertId, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'SM58 POUR CETTE DATE');
        $this->entityManager()->clear();
        self::assertSame('BETA57A', $this->lineForChannel(17)->getPreferredMicrophone());

        $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('table', 'SM58 POUR CETTE DATE');
    }

    public function testASourceCanBeAddedOnlyToTheConcertPatch(): void
    {
        $concert = $this->createConcert();
        $concertId = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $this->submitLineForm('/concerts/' . $concertId . '/sources/new', $this->newLineValues());

        self::assertResponseRedirects('/concerts/' . $concertId, 303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            22,
            $em->getRepository(PatchLine::class)->count(['sheet' => $this->sheetId]),
        );
        self::assertSame(23, $em->getRepository(PatchLine::class)->count(['sheet' => $sheetId]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'VOIX INVITÉE');
    }

    public function testDuplicateChannelsAreRejectedWithinTheConcertPatch(): void
    {
        $concert = $this->createConcert();
        $this->submitLineForm(
            '/concerts/' . $concert->getId() . '/sources/new',
            array_replace($this->newLineValues(), ['channelNumber' => '17']),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="patch_line"]', 'déjà utilisé');
        self::assertSelectorExists('a.cancel-link[href="/concerts/' . $concert->getId() . '"]');
        $this->assertLineCount(44);
    }

    public function testUnknownConcertPagesReturn404(): void
    {
        $this->client->catchExceptions(true);
        foreach (['/concerts/999999', '/concerts/999999/sources/new'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(404);
        }
    }

    private function createConcert(): Concert
    {
        return static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $this->sheet(),
                new \DateTimeImmutable('2026-11-14'),
                'Salle de test',
                'Marseille',
            );
    }
}
