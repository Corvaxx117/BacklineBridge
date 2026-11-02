<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Import;

use App\Entity\Band;
use App\Entity\BandsintownConcert;
use App\Entity\Concert;
use App\Entity\ConcertAvailability;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Enum\AvailabilityStatus;
use App\Enum\BandRole;
use App\Integration\Bandsintown\BandsintownConfiguration;
use App\Integration\Bandsintown\BandsintownGateway;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Integration\Bandsintown\Dto\EventsResult;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;

final class BandsintownImportTest extends DatabaseTestCase
{
    private string $prepareUri;
    private array $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->prepareUri = '/groupes/'.$this->bandId.'/concerts/import-bandsintown';
        $this->events = [
            $this->event('10001', '2026-12-12', 'Salle de test'),
            $this->event('10002', '2026-12-13', 'Autre salle'),
        ];
        static::getContainer()->set(
            BandsintownConfiguration::class,
            new BandsintownConfiguration((string) $this->bandId, '15466975', 'fake-key'),
        );
        $gateway = $this->createMock(BandsintownGateway::class);
        $gateway
            ->method('events')
            ->willReturnCallback(
                fn (): EventsResult => new EventsResult(
                    $this->events,
                    0,
                    new \DateTimeImmutable('now'),
                ),
            );
        static::getContainer()->set(BandsintownGateway::class, $gateway);
    }

    private function event(string $id, string $date, string $venue): BandsintownEvent
    {
        return new BandsintownEvent(
            $id,
            '15466975',
            $date,
            '20:30',
            $venue,
            'Lyon',
            'France',
            'Adresse publique',
            'Affiche publique',
            'Description importée',
            ['Avee Mana'],
            [['label' => 'Billets', 'url' => 'https://example.test/tickets']],
        );
    }

    private function preview(): string
    {
        $form = $this->client
            ->request('GET', $this->prepareUri)
            ->filter('form[name="bandsintown_fetch"]')
            ->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $uri = $this->client->getResponse()->headers->get('Location');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        return $uri;
    }

    public function testPreviewDoesNotWriteAndSelectedEventsImportAsIndependentDossiers(): void
    {
        $uri = $this->preview();
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
        self::assertSelectorCount(2, '.import-event-choice input');
        self::assertSelectorNotExists('select[name="bandsintown_import[status]"]');
        $form = $this->client->getCrawler()->filter('form[name="bandsintown_import"]')->form();
        $this->client->submit($form, [
            'bandsintown_import' => ['events' => ['10001']],
        ]);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $concert = $em->getRepository(Concert::class)->findOneBy([]);
        self::assertSame('Salle de test', $concert->getVenue());
        self::assertSame('20:30', $concert->getStartTime());
        self::assertSame('validated', $concert->getStatus()->value);
        self::assertSame($this->userId, $concert->getCreatedBy()->getId());
        self::assertNotSame($this->sheetId, $concert->getPatchSheet()->getId());
        self::assertCount(22, $concert->getPatchSheet()->getLines());
        self::assertSame(
            1,
            $em->getConnection()->fetchOne('SELECT COUNT(*) FROM concert_availability'),
        );
        self::assertSame(1, $em->getRepository(BandsintownConcert::class)->count([]));
        self::assertSame('pending', $em->getConnection()->fetchOne(
            'SELECT status FROM concert_availability WHERE concert_id = ?',
            [$concert->getId()],
        ));
        $this->client->request('GET', '/concerts/'.$concert->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.bit-concert-source', '20:30');
        self::assertSelectorTextContains('.bit-concert-source', 'Description importée');
        $this->client->catchExceptions(true);
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
    }

    public function testManualConcertIsLinkedWithoutOverwritingItsInformation(): void
    {
        $existing = static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $this->sheet(),
                new \DateTimeImmutable('2026-12-12'),
                'Salle de test',
                'Lyon',
                notes: 'Note privée à conserver',
                createdBy: $this->entityManager()->find(User::class, $this->userId),
            );
        $id = $existing->getId();
        $availability = $this->entityManager()->getRepository(ConcertAvailability::class)->findOneBy([
            'concert' => $existing,
        ]);
        self::assertInstanceOf(ConcertAvailability::class, $availability);
        $availability->respond(AvailabilityStatus::Available, 'Réponse à conserver');
        $this->entityManager()->flush();
        $this->preview();
        self::assertSelectorTextContains('.bit-preview-list', 'Rattacher cette annonce');
        $form = $this->client->getCrawler()->filter('form[name="bandsintown_import"]')->form();
        $this->client->submit($form, [
            'bandsintown_import' => ['events' => ['10001']],
        ]);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(1, $em->getRepository(Concert::class)->count([]));
        $saved = $em->find(Concert::class, $id);
        self::assertSame('Note privée à conserver', $saved->getNotes());
        self::assertSame('validated', $saved->getStatus()->value);
        $savedAvailability = $em->getRepository(ConcertAvailability::class)->findOneBy([
            'concert' => $saved,
        ]);
        self::assertInstanceOf(ConcertAvailability::class, $savedAvailability);
        self::assertSame(AvailabilityStatus::Available, $savedAvailability->getStatus());
        self::assertSame('Réponse à conserver', $savedAvailability->getComment());
        $this->preview();
        self::assertSelectorTextContains('.bit-preview-list', 'Déjà importé');
        self::assertSelectorCount(1, '.import-event-choice input');
    }

    public function testRepeatedSameDateVenueCityCreatesOnlyOneConcert(): void
    {
        $this->events = [
            $this->event('10001', '2026-12-12', 'Salle de test'),
            $this->event('10002', '2026-12-12', 'Salle de test'),
        ];
        $this->preview();
        $form = $this->client->getCrawler()->filter('form[name="bandsintown_import"]')->form();
        $this->client->submit($form, ['bandsintown_import' => ['events' => ['10001', '10002']]]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testSubmittedStatusCannotOverrideAutomaticValidation(): void
    {
        $uri = $this->preview();
        $form = $this->client->getCrawler()->filter('form[name="bandsintown_import"]')->form();
        $values = $form->getPhpValues();
        $values['bandsintown_import']['events'] = ['10001'];
        $values['bandsintown_import']['status'] = 'pending';
        $this->client->request('POST', $uri, $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testCsrfAndInjectedEventCannotBypassThePreview(): void
    {
        $uri = $this->preview();
        $form = $this->client->getCrawler()->filter('form[name="bandsintown_import"]')->form();
        $values = $form->getPhpValues();
        $values['bandsintown_import']['events'] = ['99999'];
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $values['bandsintown_import']['events'] = ['10001'];
        unset($values['bandsintown_import']['_token']);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
    }

    public function testReaderAndOtherBandCannotUseTheImport(): void
    {
        $em = $this->entityManager();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'reader@example.test',
                'Reader',
                'Another strong password 2026!',
                $em->find(Band::class, $this->bandId),
                BandRole::Viewer,
            );
        $this->client->loginUser($user);
        $this->client->catchExceptions(true);
        $this->client->request('GET', $this->prepareUri);
        self::assertResponseStatusCodeSame(403);
        $other = new Band('Other band', new TechnicalSheet()->setBandName('Other band'));
        $em->persist($other);
        $em->flush();
        $this->client->loginUser($em->find(User::class, $this->userId));
        $this->client->request(
            'GET',
            '/groupes/'.$other->getId().'/concerts/import-bandsintown',
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testBookerCanImportAndPreviewCannotBeReusedByAnotherMember(): void
    {
        $uri = $this->preview();
        $em = $this->entityManager();
        $booker = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'booker@example.test',
                'Booker',
                'Another strong password 2026!',
                $em->find(Band::class, $this->bandId),
                BandRole::Booker,
            );
        $this->client->loginUser($booker);
        $this->client->catchExceptions(true);
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
        $this->preview();
        self::assertSelectorCount(2, '.import-event-choice input');
    }

    public function testEmptyResultHasNoImportButton(): void
    {
        $this->events = [];
        $this->preview();
        self::assertSelectorTextContains('h2', 'Aucun concert');
        self::assertSelectorNotExists('form[name="bandsintown_import"] button[type="submit"]');
    }

    public function testExpiredPreviewCannotBeImported(): void
    {
        $clock = new \Symfony\Component\Clock\MockClock('2026-10-29 12:00:00 Europe/Paris');
        static::getContainer()->set(\Symfony\Component\Clock\ClockInterface::class, $clock);
        $uri = $this->preview();
        $clock->sleep(901);
        $this->client->catchExceptions(true);
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDatabaseFailureRollsBackAllConcertsSourcesAndPatchCopies(): void
    {
        $this->preview();
        $form = $this->client->getCrawler()->filter('form[name="bandsintown_import"]')->form();
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement(
            "CREATE TRIGGER fail_bit_import BEFORE INSERT ON bandsintown_concert BEGIN SELECT RAISE(ABORT, 'Import SQL failure'); END",
        );
        try {
            $this->client->submit($form, [
                'bandsintown_import' => ['events' => ['10001', '10002']],
            ]);
            self::fail('SQL failure expected');
        } catch (\Doctrine\DBAL\Exception $error) {
            self::assertStringContainsString('Import SQL failure', $error->getMessage());
        } finally {
            $connection->executeStatement('DROP TRIGGER fail_bit_import');
        }
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM concert'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM bandsintown_concert'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM concert_availability'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM technical_sheet'));
        self::assertSame(22, $connection->fetchOne('SELECT COUNT(*) FROM patch_line'));
    }
}
