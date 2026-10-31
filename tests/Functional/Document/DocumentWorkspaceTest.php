<?php

declare(strict_types=1);

namespace App\Tests\Functional\Document;

use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\DocumentCategory;
use App\Service\Document\DocumentStorage;
use App\Tests\CollaborationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class DocumentWorkspaceTest extends CollaborationTestCase
{
    public function testUploadedPdfIsSharedBetweenAccountsAndCanBeReadDownloadedAndRemoved(): void
    {
        $concertId = $this->concert()->getId();
        $this->uploadPdf($concertId);
        self::assertResponseRedirects('/concerts/'.$concertId.'#concert-documents', 303);
        $document = $this->document();
        $id = $document->getId();
        $path = static::getContainer()->get(DocumentStorage::class)->path($document);
        self::assertFileExists($path);
        self::assertSame(0600, fileperms($path) & 0777);
        self::assertSame('Responsable de test', $document->getCreatedByName());
        $this->loginRole(BandRole::Booker);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.document-grid', 'Fiche de la salle');
        self::assertSelectorExists('a[href="/concerts/'.$concertId.'#concert-documents"]');
        $this->client->request('GET', '/documents/'.$id.'/fichier');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('inline;', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        $this->client->request('GET', '/documents/'.$id.'/fichier?download=1');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
        $this->client->request('GET', '/documents/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403);
        $this->loginRole(BandRole::Manager);
        $uri = '/documents/'.$id.'/supprimer';
        $form = $this->client->request('GET', $uri)->filter('form[action="'.$uri.'"]')->form();
        self::assertFileExists($path);
        $this->client->request('POST', $uri);
        self::assertResponseStatusCodeSame(403);
        $this->client->submit($form);
        self::assertResponseRedirects('/groupes/'.$this->bandId.'/documents', 303);
        self::assertFileDoesNotExist($path);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
    }

    public function testExternalGoogleDocumentIsIndexedWithoutDownloadingItsContents(): void
    {
        $this->loginRole(BandRole::Booker);
        $uri = '/groupes/'.$this->bandId.'/documents/ajouter/lien';
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $url = 'https://docs.google.com/document/d/document-de-test/edit';
        $this->client->submit($form, ['document' => [
            'title' => 'Contrat de la tournée',
            'category' => 'contract',
            'url' => $url,
        ]]);
        self::assertResponseStatusCodeSame(303);
        $id = $this->document()->getId();
        self::assertFalse($this->document()->isPdf());
        self::assertSame(0, $this->document()->getSizeBytes());
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="'.$url.'"][target="_blank"][rel="noopener noreferrer"]');
        $this->client->request('GET', '/documents/'.$id.'/fichier');
        self::assertResponseStatusCodeSame(404);
        $uri = '/documents/'.$id.'/supprimer';
        $form = $this->client->request('GET', $uri)->filter('form[action="'.$uri.'"]')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
    }

    #[DataProvider('collaboratingRoles')]
    public function testTeamRolesCanUploadDocuments(BandRole $role): void
    {
        $this->loginRole($role);
        $this->uploadPdf();
        self::assertResponseStatusCodeSame(303);
    }

    public static function collaboratingRoles(): iterable
    {
        yield [BandRole::Manager];
        yield [BandRole::Editor];
        yield [BandRole::Booker];
    }

    public function testViewersCanReadButCannotUploadOrDelete(): void
    {
        $this->uploadPdf();
        $id = $this->document()->getId();
        $this->loginRole(BandRole::Viewer);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/documents/ajouter/"]');
        self::assertSelectorNotExists('a[href="/documents/'.$id.'/supprimer"]');
        $this->client->request('POST', '/groupes/'.$this->bandId.'/documents/ajouter/pdf');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/documents/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOtherBandsAndAnonymousVisitorsCannotAccessFiles(): void
    {
        $concert = $this->concert();
        $concertId = $concert->getId();
        $issued = $this->issue($concert);
        $this->uploadPdf($concertId);
        $id = $this->document()->getId();
        $this->loginRole(BandRole::Manager, $this->otherBand());
        foreach (['/documents/'.$id.'/fichier', '/documents/'.$id.'/supprimer', '/groupes/'.$this->bandId.'/documents', '/concerts/'.$concertId] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
        }
        $this->enter($issued->token);
        self::assertSelectorNotExists('#concert-contacts');
        self::assertSelectorNotExists('#concert-documents');
        $this->client->request('GET', '/documents/'.$id.'/fichier');
        self::assertResponseRedirects('/connexion');
    }

    public function testAnUnrelatedConcertCannotBeSelectedForADocument(): void
    {
        $other = $this->otherBand();
        $concert = static::getContainer()->get(\App\Service\Concert\ConcertCreator::class)->create(
            $other->getReferenceSheet(), new \DateTimeImmutable('2026-12-20'), 'Autre salle', 'Paris',
        );
        $id = $concert->getId();
        $uri = '/groupes/'.$this->bandId.'/documents/ajouter/lien';
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $values = $form->getPhpValues();
        $values['document']['concert'] = (string) $id;
        $values['document']['title'] = 'Document étranger';
        $values['document']['url'] = 'https://example.test/document';
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
        $this->client->request('GET', $uri.'?concert='.$id);
        self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('invalidUrls')]
    public function testUnsafeLinksCannotBeAdded(string $url): void
    {
        $uri = '/groupes/'.$this->bandId.'/documents/ajouter/lien';
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $values = $form->getPhpValues();
        $values['document']['title'] = 'Lien invalide';
        $values['document']['url'] = $url;
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
    }

    public static function invalidUrls(): iterable
    {
        yield ['javascript:alert(1)'];
        yield ['data:text/html,test'];
        yield ['http://example.test/file'];
        yield ['https://user:password@example.test/file'];
        yield [''];
    }

    public function testFakePdfsAndPdfsOverTenMegabytesAreRejected(): void
    {
        $this->uploadPdf(file: $this->pdfFile('<?php echo "not a pdf";'));
        self::assertResponseStatusCodeSame(422);
        $this->uploadPdf(file: $this->pdfFile("%PDF-1.4\n".str_repeat('x', 10 * 1024 * 1024)));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
    }

    public function testQuotaIsEnforcedBeforeStoringAnotherPdf(): void
    {
        $directory = $this->documentsDirectory.'/'.$this->bandId;
        mkdir($directory, 0700, true);
        $file = fopen($directory.'/reserved.bin', 'wb');
        ftruncate($file, static::getContainer()->get(DocumentStorage::class)->getQuotaBytes());
        fclose($file);
        $this->uploadPdf();
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error-summary', 'quota');
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
        self::assertCount(1, glob($directory.'/*'));
    }

    public function testMissingCsrfDoesNotAddALink(): void
    {
        $this->client->request('POST', '/groupes/'.$this->bandId.'/documents/ajouter/lien', [
            'document' => ['title' => 'Lien', 'url' => 'https://example.test/document', 'category' => 'technical'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandDocument::class)->count([]));
    }

    public function testSearchAndCategoryRemainScopedToTheCurrentBand(): void
    {
        $em = $this->entityManager();
        $band = $em->find(Band::class, $this->bandId);
        $em->persist(new BandDocument($band, null, null, 'Fiche Lyon', DocumentCategory::Technical, 'accueil', 'https://example.test/a', null, null, 0));
        $em->persist(new BandDocument($this->otherBand(), null, null, 'Document confidentiel', DocumentCategory::Technical, 'accueil', 'https://example.test/b', null, null, 0));
        $em->flush();
        $this->client->request('GET', '/groupes/'.$this->bandId.'/documents?q=accueil&category=technical');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.document-grid', 'Fiche Lyon');
        self::assertSelectorTextNotContains('main', 'Document confidentiel');
        $this->client->request('GET', '/groupes/'.$this->bandId.'/documents?category=contract');
        self::assertSelectorNotExists('.document-card');
    }

    private function otherBand(): Band
    {
        $sheet = new TechnicalSheet()->setBandName('Autre groupe');
        $band = new Band('Autre groupe', $sheet);
        $this->entityManager()->persist($band);
        $this->entityManager()->flush();

        return $band;
    }
}
