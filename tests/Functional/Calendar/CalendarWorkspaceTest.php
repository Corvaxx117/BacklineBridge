<?php

declare(strict_types=1);

namespace App\Tests\Functional\Calendar;

use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Integration\GoogleCalendar\Dto\AgendaEvent;
use App\Integration\GoogleCalendar\Dto\AgendaListing;
use App\Integration\GoogleCalendar\Dto\EventDraft;
use App\Integration\GoogleCalendar\Dto\PublicationResult;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Integration\GoogleCalendar\GoogleCalendarGateway;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CalendarWorkspaceTest extends VenueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $em = $this->entityManager();
        $em->persist(new MemberProfile(
            $em->find(Band::class, $this->bandId),
            $em->find(User::class, $this->userId),
            'Musicien test',
            'Guitare',
            MemberKind::Musician,
        ));
        $em->flush();
        static::getContainer()->get('cache.google_calendar')->clear();
    }

    private function configure(string $bandId = ''): void
    {
        static::getContainer()->set(GoogleCalendarConfiguration::class, new GoogleCalendarConfiguration(
            '' !== $bandId ? $bandId : (string) $this->bandId,
            'group@example.test', '/private/key.json', str_repeat('a', 32), 'Europe/Paris',
        ));
    }

    public function testMusicianCanReadPrivateCalendarAndHtmlIsEscaped(): void
    {
        $this->configure();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::once())->method('events')->willReturn(new AgendaListing([
            new AgendaEvent('<script>Répétition</script>', 'Local du groupe', new \DateTimeImmutable('2026-12-12 20:00'), new \DateTimeImmutable('2026-12-12 21:00'), false, null),
        ], false, new \DateTimeImmutable('now')));
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.calendar-list', '<script>Répétition</script>');
        self::assertSelectorNotExists('.calendar-list script');
        self::assertSelectorExists('nav a[href="/groupes/'.$this->bandId.'/agenda"]');
        // La deuxième consultation réutilise le cache de cinq minutes.
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda');
        self::assertResponseIsSuccessful();
    }

    #[DataProvider('readOnlyMembers')]
    public function testAllMembersCanReadButPublishingKeepsItsOwnPermission(MemberKind $kind, BandRole $role): void
    {
        $this->configure();
        $concert = $this->concert();
        $band = $concert->getBand();
        $user = static::getContainer()->get(UserCreator::class)->create('excluded@example.test', 'Profil exclu', 'CompteTest2026!', $band, $role);
        $em = $this->entityManager();
        $em->persist(new MemberProfile($band, $user, 'Profil exclu', 'Équipe', $kind));
        $em->flush();
        $this->client->loginUser($user);
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::exactly(2))->method('events')->willReturn(new AgendaListing([], false, new \DateTimeImmutable('now')));
        $gateway->expects(self::never())->method('publish');
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-description', 'Avee Mana Partagé');
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda/evenements?start=2026-11-01&end=2026-12-01');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/concerts/'.$concert->getId().'/agenda-google', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/concerts/'.$concert->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#concert-calendar');
        self::assertSelectorNotExists('#concert-calendar form');
        self::assertSelectorExists('nav a[href="/groupes/'.$this->bandId.'/agenda"]');
    }

    public static function readOnlyMembers(): iterable
    {
        yield 'Marty' => [MemberKind::Engineer, BandRole::Manager];
        yield 'Romain' => [MemberKind::Booker, BandRole::Booker];
        yield 'Lecture seule' => [MemberKind::Musician, BandRole::Viewer];
        yield 'Régie technique' => [MemberKind::Engineer, BandRole::Editor];
    }

    #[DataProvider('hours')]
    public function testPublicationRespectsDeclaredHourOrUsesTwentyOClock(?string $hour, string $expected): void
    {
        $this->configure();
        $concert = $this->concert();
        $concert->setStartTime($hour);
        $this->entityManager()->flush();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::once())->method('publish')->willReturnCallback(function (EventDraft $draft) use ($expected): PublicationResult {
            self::assertSame($expected, $draft->start->format('H:i'));
            self::assertSame(3600, $draft->end->getTimestamp() - $draft->start->getTimestamp());
            self::assertSame('Europe/Paris', $draft->start->getTimezone()->getName());
            self::assertStringContainsString('Salle de test', $draft->title);
            self::assertStringNotContainsString('Accueil', json_encode($draft->payload()));

            return new PublicationResult(true, 'https://calendar.google.com/calendar/event?eid=test');
        });
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $form = $this->client->request('GET', '/concerts/'.$concert->getId())->filter('#concert-calendar form')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'ajouté');
        self::assertSelectorExists('#concert-calendar a[href="https://calendar.google.com/calendar/event?eid=test"]');
    }

    public static function hours(): iterable
    {
        yield 'non renseignée' => [null, '20:00'];
        yield 'renseignée' => ['21:45', '21:45'];
        yield 'minuit explicite' => ['00:00', '00:00'];
    }

    public function testPublicationRequiresPostAndCsrf(): void
    {
        $this->configure();
        $concert = $this->concert();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::never())->method('publish');
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $this->client->request('GET', '/concerts/'.$concert->getId().'/agenda-google');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/concerts/'.$concert->getId().'/agenda-google', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDisconnectedCalendarDoesNotCallGoogle(): void
    {
        $this->configure('999999');
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::never())->method('events');
        $gateway->expects(self::never())->method('publish');
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.calendar-empty', 'pas encore connecté');
        $concert = $this->concert();
        $this->client->request('GET', '/concerts/'.$concert->getId());
        self::assertSelectorNotExists('#concert-calendar form');
    }

    public function testGoogleFailureLeavesTheConcertUntouched(): void
    {
        $this->configure();
        $concert = $this->concert();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->method('publish')->willThrowException(new GoogleCalendarUnavailable('Google est momentanément indisponible.'));
        $gateway->method('events')->willThrowException(new GoogleCalendarUnavailable('Google est momentanément indisponible.'));
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $form = $this->client->request('GET', '/concerts/'.$concert->getId())->filter('#concert-calendar form')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'indisponible');
        self::assertSame('Accueil à 16 h.', $concert->getNotes());
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda');
        self::assertResponseStatusCodeSame(503);
    }

    public function testGuestAndAnotherBandHaveNoCalendarAccess(): void
    {
        $this->configure();
        $other = new Band('Autre groupe', new \App\Entity\TechnicalSheet());
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $this->client->request('GET', '/groupes/'.$other->getId().'/agenda');
        self::assertResponseStatusCodeSame(403);
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda');
        self::assertResponseRedirects('/connexion');
    }

    public function testConcertFormPersistsOptionalStartTime(): void
    {
        $this->configure();
        $form = $this->client->request('GET', '/concerts/new')->filter('form[name="concert"]')->form();
        $this->client->submit($form, ['concert' => [
            'date' => '2026-12-12', 'startTime' => '22:15', 'venue' => 'Salle', 'city' => 'Lyon', 'status' => 'validated',
        ]]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('22:15', $this->entityManager()->getConnection()->fetchOne('SELECT start_time FROM concert'));
        $id = $this->entityManager()->getConnection()->fetchOne('SELECT id FROM concert');
        $form = $this->client->request('GET', '/concerts/'.$id.'/edit')->filter('form[name="concert"]')->form();
        $this->client->submit($form, ['concert' => ['startTime' => '']]);
        self::assertResponseStatusCodeSame(303);
        self::assertNull($this->entityManager()->getConnection()->fetchOne('SELECT start_time FROM concert'));
    }

    public function testCalendarFeedUsesTheSelectedPeriodAndPreservesWholeDayBoundaries(): void
    {
        $this->configure();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::once())->method('events')->willReturnCallback(function (\DateTimeImmutable $from, \DateTimeImmutable $until): AgendaListing {
            self::assertSame('2026-10-26 00:00 Europe/Paris', $from->format('Y-m-d H:i e'));
            self::assertSame('2026-12-07', $until->format('Y-m-d'));

            return new AgendaListing([
                new AgendaEvent('<script>Résidence</script>', 'Local', new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable('2026-11-14'), true, null),
            ], false, new \DateTimeImmutable('now'));
        });
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $uri = '/groupes/'.$this->bandId.'/agenda/evenements?start=2026-10-26&end=2026-12-07';
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control', ''));
        self::assertStringContainsString('private', $this->client->getResponse()->headers->get('Cache-Control', ''));
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('2026-11-12', $data['events'][0]['start']);
        self::assertSame('2026-11-15', $data['events'][0]['end']);
        self::assertSame('<script>Résidence</script>', $data['events'][0]['title']);
        self::assertStringNotContainsString('<script>', $this->client->getResponse()->getContent());
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();
    }

    #[DataProvider('invalidPeriods')]
    public function testInvalidCalendarPeriodsAreRefusedBeforeContactingGoogle(string $query): void
    {
        $this->configure();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::never())->method('events');
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda/evenements?'.$query);
        self::assertResponseStatusCodeSame(400);
    }

    public static function invalidPeriods(): iterable
    {
        yield 'sans dates' => [''];
        yield 'date impossible' => ['start=2026-02-30&end=2026-03-10'];
        yield 'période inversée' => ['start=2026-12-01&end=2026-11-01'];
        yield 'période excessive' => ['start=2026-01-01&end=2026-06-01'];
        yield 'année hors limites' => ['start=3000-01-01&end=3000-02-01'];
        yield 'paramètre tableau' => ['start[]=2026-11-01&end=2026-12-01'];
    }

    public function testPublishingInvalidatesBothMonthAndWeekCaches(): void
    {
        $this->configure();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->expects(self::exactly(4))->method('events')->willReturn(new AgendaListing([], false, new \DateTimeImmutable('now')));
        $gateway->expects(self::once())->method('publish')->willReturn(new PublicationResult(true, null));
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $uris = [
            '/groupes/'.$this->bandId.'/agenda/evenements?start=2026-10-26&end=2026-12-07',
            '/groupes/'.$this->bandId.'/agenda/evenements?start=2026-11-02&end=2026-11-09',
        ];
        foreach ($uris as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseIsSuccessful();
        }
        $concert = $this->concert();
        $form = $this->client->request('GET', '/concerts/'.$concert->getId())->filter('#concert-calendar form')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        foreach ($uris as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseIsSuccessful();
        }
    }

    public function testFeedFailureIsReportedWithoutChangingConcerts(): void
    {
        $this->configure();
        $gateway = $this->createMock(GoogleCalendarGateway::class);
        $gateway->method('events')->willThrowException(new GoogleCalendarUnavailable('Google est momentanément indisponible.'));
        static::getContainer()->set(GoogleCalendarGateway::class, $gateway);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/agenda/evenements?start=2026-11-01&end=2026-12-01');
        self::assertResponseStatusCodeSame(503);
        self::assertJsonStringEqualsJsonString('{"error":"Google est momentanément indisponible."}', $this->client->getResponse()->getContent());
    }
}
