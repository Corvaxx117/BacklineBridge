<?php

declare(strict_types=1);

namespace App\Tests\Functional\Document;

use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\DocumentCategory;
use App\Service\Concert\ConcertCreator;
use App\Tests\CollaborationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class RoadmapTest extends CollaborationTestCase
{
    public function testConcertShortcutPrefillsTheDocumentAndSharesItWithTheTeam(): void
    {
        $concertId = $this->concert()->getId();
        $crawler = $this->client->request('GET', '/concerts/'.$concertId);
        $this->client->click($crawler->selectLink('Ajouter la feuille de route')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ajouter la feuille de route');
        self::assertInputValueSame('document[title]', 'Feuille de route — Salle de test — 12/12/2026');
        self::assertSelectorNotExists('select[name="document[concert]"]');
        self::assertSelectorNotExists('select[name="document[category]"]');
        self::assertSelectorExists('a.back-link[href="/concerts/'.$concertId.'#concert-documents"]');
        $form = $this->client->getCrawler()->filter('form[name="document"]')->form();
        $field = $form['document[file]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($this->pdfFile());
        $this->client->submit($form);
        self::assertResponseRedirects('/concerts/'.$concertId.'#concert-documents', 303);
        $document = $this->document();
        $documentId = $document->getId();
        self::assertSame($concertId, $document->getConcert()->getId());
        self::assertSame(DocumentCategory::Roadmap, $document->getCategory());
        $this->loginRole(BandRole::Booker);
        $this->client->request('GET', '/concerts/'.$concertId);
        self::assertSelectorTextContains('#concert-documents', 'Feuilles de route');
        self::assertSelectorTextContains('#concert-documents', 'Feuille de route — Salle de test');
        $this->client->request('GET', '/documents/'.$documentId.'/fichier');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        $this->client->request('GET', '/groupes/'.$this->bandId.'/documents?category=roadmap');
        self::assertSelectorTextContains('.document-grid', 'Feuille de route — Salle de test');
    }

    #[DataProvider('teamRoles')]
    public function testAllCollaboratorsCanAddARoadmapLink(BandRole $role): void
    {
        $concertId = $this->concert()->getId();
        $this->loginRole($role);
        $this->client->request('GET', $this->url($concertId));
        $this->client->clickLink('Ajouter un lien');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[type="file"]');
        $form = $this->client->getCrawler()->filter('form[name="document"]')->form();
        $this->client->submit($form, [
            'document[url]' => 'https://docs.google.com/document/d/feuille-de-route-test/edit',
        ]);
        self::assertResponseRedirects('/concerts/'.$concertId.'#concert-documents', 303);
        $document = $this->document();
        self::assertSame(DocumentCategory::Roadmap, $document->getCategory());
        self::assertSame($concertId, $document->getConcert()->getId());
        self::assertSame(0, $document->getSizeBytes());
        self::assertFalse($document->isPdf());
    }

    public static function teamRoles(): iterable
    {
        yield 'musiciens et ingénieur son' => [BandRole::Manager];
        yield 'booker' => [BandRole::Booker];
        yield 'collaborateur' => [BandRole::Editor];
    }

    public function testAConcertContextCannotBeForgedInTheSubmittedForm(): void
    {
        $concertId = $this->concert()->getId();
        $otherConcertId = $this->concert()->getId();
        $uri = $this->url($concertId, 'lien');
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $values = $form->getPhpValues();
        $values['document']['url'] = 'https://example.test/roadmap';
        $values['document']['concert'] = (string) $otherConcertId;
        $values['document']['category'] = 'technical';
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
    }

    public function testAnotherBandsConcertCannotBeUsedAsContext(): void
    {
        $em = $this->entityManager();
        $otherBand = new Band('Autre groupe', new TechnicalSheet()->setBandName('Autre groupe'));
        $em->persist($otherBand);
        $em->flush();
        $concert = static::getContainer()->get(ConcertCreator::class)->create(
            $otherBand->getReferenceSheet(),
            new \DateTimeImmutable('2026-12-20'),
            'Autre salle',
            'Paris',
        );
        $uri = $this->url($concert->getId());
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $uri);
        self::assertResponseStatusCodeSame(404);
    }

    public function testReadOnlyAndVenueGuestsCannotUploadToTheGroup(): void
    {
        $concert = $this->concert();
        $issued = $this->issue($concert);
        $uri = $this->url($concert->getId());
        $this->loginRole(BandRole::Viewer);
        $this->client->request('GET', '/concerts/'.$concert->getId());
        self::assertSelectorNotExists('a[href*="usage=roadmap"]');
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $uri);
        self::assertResponseStatusCodeSame(403);
        $this->enter($issued->token);
        $this->client->request('GET', $uri);
        self::assertResponseRedirects('/connexion');
    }

    public function testTheGeneralLibraryStillOffersConcertAndCategoryChoices(): void
    {
        $concertId = $this->concert()->getId();
        $uri = '/groupes/'.$this->bandId.'/documents/ajouter/lien';
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        self::assertSelectorExists('select[name="document[concert]"]');
        self::assertSelectorExists('select[name="document[category]"] option[value="roadmap"]');
        $this->client->submit($form, [
            'document[title]' => 'Feuille reçue par e-mail',
            'document[category]' => 'roadmap',
            'document[concert]' => (string) $concertId,
            'document[url]' => 'https://example.test/roadmap',
        ]);
        self::assertResponseRedirects('/concerts/'.$concertId.'#concert-documents', 303);
    }

    public function testCsrfIsStillRequiredForTheRoadmapShortcut(): void
    {
        $uri = $this->url($this->concert()->getId(), 'lien');
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $values = $form->getPhpValues();
        $values['document']['url'] = 'https://example.test/roadmap';
        unset($values['document']['_token']);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
    }

    private function url(int $concertId, string $source = 'pdf'): string
    {
        return '/groupes/'.$this->bandId.'/documents/ajouter/'.$source.'?concert='.$concertId.'&usage=roadmap';
    }
}
