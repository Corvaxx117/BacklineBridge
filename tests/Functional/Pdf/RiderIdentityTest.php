<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pdf;

use App\Dto\Technical\TechnicalIdentity;
use App\Entity\Band;
use App\Service\Pdf\TechnicalDossierFactory;
use App\Tests\VenueTestCase;
use Twig\Environment;

final class RiderIdentityTest extends VenueTestCase
{
    private function identity(string $name = 'Contact fictif'): TechnicalIdentity
    {
        return TechnicalIdentity::fromArray([
            'style' => 'Psychedelic / Indie / Garage',
            'description' => 'Powerfull live energy, fuzzy guitars, strong low-end and wide dynamics',
            'contacts' => [['name' => $name, 'role' => 'Son / FOH', 'email' => 'sound@example.test', 'phone' => '01 00 00 00 00']],
        ]);
    }

    public function testTheDocumentContainsThePublicIdentityAndNoApplicationBrand(): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $band->setTechnicalIdentity($this->identity());
        $this->entityManager()->flush();
        $dossier = static::getContainer()->get(TechnicalDossierFactory::class)->forSheet($this->sheet());
        self::assertSame('Contact fictif', $dossier->identity->contacts[0]['name']);
        self::assertSame('drums', $dossier->lines[0]['section']);
        $html = static::getContainer()->get(Environment::class)->render('pdf/technical-sheet.html.twig', [
            'dossier' => $dossier, 'stageImage' => '', 'stageWidth' => 500, 'stageHeight' => 300,
        ]);
        self::assertStringNotContainsString('BacklineBridge', $html);
        self::assertStringContainsString('Contact fictif', $html);
        self::assertStringContainsString('sound@example.test', $html);
        self::assertStringContainsString('Powerfull live energy', $html);
        self::assertStringContainsString('input-family-drums', $html);
        self::assertStringContainsString('equipment-family-drums', $html);
        self::assertStringContainsString('equipment-family-guitar', $html);
        self::assertStringContainsString('equipment-family-bass', $html);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('.rider-style', 'Psychedelic / Indie / Garage');
        self::assertSelectorTextContains('.rider-contacts', 'Contact fictif');
        self::assertSelectorCount(9, '.patch-table tr[data-technical-family="drums"]');
        self::assertSelectorCount(2, '.stage-amp[data-technical-family="guitar"]');
    }

    public function testContactsRemainAccessibleWhenTheSheetHasNoTechnicalNotice(): void
    {
        $this->sheet()->setNotices([]);
        $this->entityManager()->find(Band::class, $this->bandId)->setTechnicalIdentity($this->identity());
        $this->entityManager()->flush();
        $concert = $this->concert();
        foreach (['/', '/concerts/'.$concert->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.rider-contacts', 'Contact fictif');
        }
    }

    public function testPublishedCoordinatesStayFrozenAndNewConcertsUseTheCurrentBandIdentity(): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $band->setTechnicalIdentity($this->identity('Premier contact'));
        $this->entityManager()->flush();
        $issued = $this->issue();
        $publication = $issued->invitation->getPublication();
        $band->setTechnicalIdentity($this->identity('Nouveau contact'));
        $this->entityManager()->flush();
        $factory = static::getContainer()->get(TechnicalDossierFactory::class);
        self::assertSame('Premier contact', $factory->forPublication($publication)->identity->contacts[0]['name']);
        self::assertSame('Nouveau contact', $factory->forSheet($publication->getConcert()->getPatchSheet())->identity->contacts[0]['name']);
    }
}
