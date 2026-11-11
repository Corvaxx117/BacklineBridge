<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Import;

use App\Entity\Band;
use App\Entity\BandNotification;
use App\Entity\BandsintownConcert;
use App\Entity\Concert;
use App\Entity\ConcertContact;
use App\Entity\OrfeoConcert;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\ConcertImportance;
use App\Enum\ConcertStatus;
use App\Enum\ContactRole;
use App\Enum\NotificationType;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Integration\Orfeo\Dto\EventsResult;
use App\Integration\Orfeo\Dto\OrfeoContact;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Integration\Orfeo\OrfeoConfiguration;
use App\Integration\Orfeo\OrfeoGateway;
use App\Service\Concert\ConcertCreator;
use App\Service\Notification\NotificationTarget;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;

final class OrfeoImportTest extends DatabaseTestCase
{
    private string $prepareUri;
    private string $reviewUri;
    /** @var list<OrfeoEvent> */
    private array $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->client->catchExceptions(true);
        $this->prepareUri = '/groupes/'.$this->bandId.'/concerts/import-orfeo';
        $this->events = [$this->event()];
        static::getContainer()->set(OrfeoConfiguration::class,
            new OrfeoConfiguration((string) $this->bandId, 'synthetic-orfeo-key', '301', '44', 'Europe/Paris', false));
        $gateway = $this->createMock(OrfeoGateway::class);
        $gateway->method('events')->willReturnCallback(fn (): EventsResult => new EventsResult($this->events, 0, new \DateTimeImmutable('now', new \DateTimeZone('UTC'))));
        static::getContainer()->set(OrfeoGateway::class, $gateway);
    }

    private function event(bool $confirmed = true, bool $cancelled = false, string $phone = '0102030405'): OrfeoEvent
    {
        return new OrfeoEvent(901, 1001, 301, '2027-01-15', null, 'Salle fictive', 'Ville fictive', null,
            $confirmed ? 44 : 45, $confirmed ? 'Confirmé' : 'Option', $confirmed, $cancelled,
            [new OrfeoContact('linked:88', 'Contact fictif', ContactRole::StageManager, 'regie@example.test', $phone, null)]);
    }

    private function preview(): void
    {
        $crawler = $this->client->request('GET', $this->prepareUri);
        $form = $crawler->selectButton('Charger l’aperçu Orfeo')->form();
        $this->client->submit($form, ['orfeo_fetch[period]' => 'all']);
        self::assertResponseStatusCodeSame(303);
        $this->reviewUri = $this->client->getResponse()->headers->get('Location');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    /** @param array<string, mixed> $overrides */
    private function apply(array $overrides = []): void
    {
        $crawler = $this->client->request('GET', $this->reviewUri);
        $values = $crawler->filter('form[name="orfeo_import"]')->form()->getPhpValues();
        $values['orfeo_import']['events'] = ['901:1001'];
        foreach ($overrides as $key => $value) {
            $values['orfeo_import'][$key] = $value;
        }
        $this->client->request('POST', $this->reviewUri, $values);
    }

    public function testPreviewFormProvidesLoadingFeedbackWithoutRequiringJavascript(): void
    {
        $this->client->request('GET', $this->prepareUri);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('script[src^="/scripts/import-preview.js"]');
        self::assertSelectorExists('form[method="post"][data-import-preview] button[data-import-submit]:not([disabled])');
        self::assertSelectorExists('[data-import-status][role="status"][hidden]');
        self::assertSelectorExists('input[name="orfeo_fetch[_token]"]');
    }

    public function testPreviewIsReadonlyAndImportCreatesOneConcertWithItsOwnPatchAndContacts(): void
    {
        $this->preview();
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
        self::assertSelectorExists('link[href^="/styles/group.css"]');
        self::assertSelectorExists('link[href^="/styles/concert-import.css"]');
        self::assertSelectorTextContains('body', 'Horaire à confirmer');
        self::assertSelectorTextContains('body', 'Contact fictif');
        $this->apply();
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $concert = $em->getRepository(Concert::class)->findOneBy([]);
        self::assertInstanceOf(Concert::class, $concert);
        self::assertSame(ConcertStatus::Validated, $concert->getStatus());
        self::assertNull($concert->getStartTime());
        self::assertNotSame($this->sheet()->getId(), $concert->getPatchSheet()->getId());
        self::assertSame(1, $em->getRepository(ConcertContact::class)->count(['concert' => $concert]));
        self::assertSame(1, $em->getRepository(OrfeoConcert::class)->count([]));
        $notice = $em->getRepository(BandNotification::class)->findOneBy(['type' => NotificationType::OrfeoConcertsImported]);
        self::assertNotNull($notice);
        self::assertSame('/concerts', static::getContainer()->get(NotificationTarget::class)->url($notice));
        $this->client->request('GET', '/concerts/'.$concert->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.concert-orfeo-source', 'Orfeo');
    }

    public function testRepeatingTheImportDoesNotDuplicateConcertsOrContacts(): void
    {
        $this->preview();
        $this->apply();
        self::assertResponseStatusCodeSame(303);
        $this->preview();
        $this->apply();
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->entityManager()->getRepository(Concert::class)->count([]));
        self::assertSame(1, $this->entityManager()->getRepository(ConcertContact::class)->count([]));
        self::assertSame(1, $this->entityManager()->getRepository(OrfeoConcert::class)->count([]));
    }

    public function testOptionsRemainPendingAndCancelledDatesCannotBeImported(): void
    {
        $this->events = [$this->event(false)];
        $this->preview();
        $this->apply();
        self::assertResponseStatusCodeSame(303);
        self::assertSame(ConcertStatus::Pending, $this->entityManager()->getRepository(Concert::class)->findOneBy([])->getStatus());
        $this->events = [$this->event(false, true)];
        $this->preview();
        self::assertSelectorTextContains('body', 'Annulation signalée');
        $values = ['orfeo_import' => ['events' => ['901:1001']]];
        $this->client->request('POST', $this->reviewUri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testLinkingPreservesManualNotesAndTechnicalContent(): void
    {
        $concert = static::getContainer()->get(ConcertCreator::class)->create($this->sheet(),
            new \DateTimeImmutable('2027-01-15'), 'Salle fictive', 'Ville fictive', notes: 'Note interne à garder');
        $sheetId = $concert->getPatchSheet()->getId();
        $concert->setImportance(ConcertImportance::Major, 'Pros attendus');
        $this->entityManager()->flush();
        $this->preview();
        $this->apply(['updateExisting' => '1']);
        self::assertResponseStatusCodeSame(303);
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Concert::class, $concert->getId());
        self::assertSame('Note interne à garder', $reloaded->getNotes());
        self::assertSame($sheetId, $reloaded->getPatchSheet()->getId());
        self::assertSame(ConcertImportance::Major, $reloaded->getImportance());
        self::assertSame('Pros attendus', $reloaded->getImportanceNote());
        self::assertSame(1, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testManualContactEditsAreNotOverwrittenByARefresh(): void
    {
        $this->preview();
        $this->apply();
        $contact = $this->entityManager()->getRepository(ConcertContact::class)->findOneBy([]);
        $contact->update($contact->getName(), $contact->getRole(), $contact->getEmail(), '0699999999', 'Appeler après 18 h');
        $this->entityManager()->flush();
        $this->events = [$this->event(phone: '0600000000')];
        $this->preview();
        $this->apply(['updateExisting' => '1']);
        self::assertResponseStatusCodeSame(303);
        $this->entityManager()->clear();
        $contact = $this->entityManager()->getRepository(ConcertContact::class)->findOneBy([]);
        self::assertSame('0699999999', $contact->getPhone());
        self::assertSame('Appeler après 18 h', $contact->getNotes());
        self::assertSame(1, $this->entityManager()->getRepository(ConcertContact::class)->count([]));
    }

    public function testAStalePreviewCannotOverwriteAConcert(): void
    {
        $concert = static::getContainer()->get(ConcertCreator::class)->create($this->sheet(),
            new \DateTimeImmutable('2027-01-15'), 'Salle fictive', 'Ville fictive');
        $this->preview();
        $this->entityManager()->getConnection()->executeStatement('UPDATE concert SET revision=revision+1 WHERE id=?', [$concert->getId()]);
        $this->entityManager()->clear();
        $this->apply(['updateExisting' => '1']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(0, $this->entityManager()->getRepository(OrfeoConcert::class)->count([]));
        self::assertSame(0, $this->entityManager()->getRepository(ConcertContact::class)->count([]));
    }

    public function testAnotherBandAndForgedTargetsCannotBeUsed(): void
    {
        $other = new Band('Autre groupe', new TechnicalSheet());
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $this->client->request('GET', '/groupes/'.$other->getId().'/concerts/import-orfeo');
        self::assertResponseStatusCodeSame(403);
        $this->preview();
        $this->apply(['targets' => ['event_901_1001' => '999999']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
        $this->client->request('POST', $this->prepareUri, ['orfeo_fetch' => ['period' => 'all']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAValidatedBandsintownConcertIsNotDowngradedByAnOrfeoOption(): void
    {
        $concert = static::getContainer()->get(ConcertCreator::class)->create($this->sheet(),
            new \DateTimeImmutable('2027-01-15'), 'Salle fictive', 'Ville fictive', ConcertStatus::Validated);
        $this->entityManager()->persist(new BandsintownConcert($concert, new BandsintownEvent(
            '2001', 'artist', '2027-01-15', '20:00', 'Salle fictive', 'Ville fictive', 'France', '', '', '', [], [],
        )));
        $this->entityManager()->flush();
        $this->events = [$this->event(false)];
        $this->preview();
        $this->apply(['updateExisting' => '1']);
        self::assertResponseStatusCodeSame(303);
        $this->entityManager()->clear();
        self::assertSame(ConcertStatus::Validated, $this->entityManager()->find(Concert::class, $concert->getId())->getStatus());
    }

    public function testASourceChangedAfterPreviewCannotBeOverwritten(): void
    {
        $this->preview();
        $this->apply();
        $this->preview();
        $this->entityManager()->getConnection()->executeStatement('UPDATE orfeo_concert SET revision = revision + 1');
        $this->entityManager()->clear();
        $this->apply(['updateExisting' => '1']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testAPreviewIsBoundToItsUserAndExpires(): void
    {
        $this->preview();
        $session = $this->client->getRequest()->getSession();
        $previews = $session->get('orfeo_previews');
        foreach ($previews as &$preview) {
            $preview['expires'] = 0;
        }
        unset($preview);
        $session->set('orfeo_previews', $previews);
        $session->save();
        $this->client->request('GET', $this->reviewUri);
        self::assertResponseStatusCodeSame(404);
        $this->preview();
        $other = static::getContainer()->get(UserCreator::class)->create('other@example.test', 'Autre responsable',
            'CompteTest2026!', $this->entityManager()->find(Band::class, $this->bandId), BandRole::Manager);
        $this->client->loginUser($other);
        $this->client->request('GET', $this->reviewUri);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheBookerCanImportButAViewerCannot(): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $creator = static::getContainer()->get(UserCreator::class);
        $booker = $creator->create('booker@example.test', 'Booker fictif', 'CompteTest2026!', $band, BandRole::Booker);
        $this->client->loginUser($booker);
        $this->preview();
        $this->apply();
        self::assertResponseStatusCodeSame(303);
        $viewer = $creator->create('viewer@example.test', 'Lecture seule', 'CompteTest2026!', $this->entityManager()->find(Band::class, $this->bandId), BandRole::Viewer);
        $this->client->loginUser($viewer);
        $this->client->request('GET', $this->prepareUri);
        self::assertResponseStatusCodeSame(403);
    }
}
