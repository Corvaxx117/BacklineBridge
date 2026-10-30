<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Availability;

use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\ConcertAvailability;
use App\Entity\User;
use App\Enum\AvailabilityStatus;
use App\Enum\BandRole;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AvailabilityTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
    }

    public function testCreationRecordsTheSignedInCreatorAndRequestsAllFiveManagersIncludingTheEngineer(): void
    {
        foreach (['Rémi', 'Franky', 'Sylou', 'Marty'] as $index => $name) {
            $this->person('member'.$index, $name);
        }
        $this->person('booker', 'Romain', BandRole::Booker);
        $crawler = $this->client->request('GET', '/concerts/new');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => ['date' => '2026-12-12', 'venue' => 'Concert équipe', 'city' => 'Lyon'],
        ]);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $concert = $em->getRepository(Concert::class)->findOneBy([]);
        self::assertSame($this->userId, $concert->getCreatedBy()->getId());
        self::assertSame('Responsable de test', $concert->getCreatedByName());
        self::assertNotNull($concert->getCreatedAt());
        $records = $em->getRepository(ConcertAvailability::class)->findBy(['concert' => $concert]);
        self::assertCount(5, $records);
        foreach ($records as $record) {
            self::assertSame(AvailabilityStatus::Pending, $record->getStatus());
            self::assertNull($record->getAnsweredAt());
        }
        $this->client->followRedirect();
        self::assertSelectorTextContains('.concert-creator', 'Responsable de test');
        self::assertSelectorTextContains('.availability-roster', 'Marty');
        self::assertSelectorTextNotContains('.availability-roster', 'Romain');
        self::assertSelectorTextContains('.availability-counter', '0 / 5');
        $this->client->request('GET', '/concerts');
        self::assertSelectorTextContains('.concert-creator', 'Responsable de test');
        self::assertSelectorTextContains('.concert-availability-link', 'À confirmer');
    }

    public function testEachManagerCanOnlyAnswerForThemselfAndChangeOrWithdrawTheirAnswer(): void
    {
        $marty = $this->person('engineer', 'Marty');
        $martyId = $marty->getId();
        $concertId = $this->concert()->getId();
        $this->reply($concertId, [
            'status' => 'available',
            'comment' => "  Prêt à 16 h.\n<script>privé</script>  ",
        ]);
        self::assertResponseRedirects('/concerts/'.$concertId.'#disponibilites', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.availability-counter', '1 / 2');
        self::assertSelectorNotExists('.availability-roster script');
        $em = $this->entityManager();
        $em->clear();
        $own = $em
            ->getRepository(ConcertAvailability::class)
            ->findOneBy(['concert' => $concertId, 'user' => $this->userId]);
        self::assertSame(AvailabilityStatus::Available, $own->getStatus());
        self::assertSame("Prêt à 16 h.\n<script>privé</script>", $own->getComment());
        self::assertNotNull($own->getAnsweredAt());
        self::assertSame(
            AvailabilityStatus::Pending,
            $em
                ->getRepository(ConcertAvailability::class)
                ->findOneBy(['concert' => $concertId, 'user' => $martyId])
                ->getStatus(),
        );
        $this->client->loginUser($marty);
        $this->reply($concertId, ['status' => 'unavailable', 'comment' => 'Autre engagement.']);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.availability-alert', '1 personne indisponible');
        $this->reply($concertId, ['status' => 'available', 'comment' => '']);
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '.availability-complete',
            'Tous les responsables inscrits',
        );
        $this->reply($concertId, ['status' => 'pending', 'comment' => 'À revoir.']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.availability-counter', '1 / 2');
    }

    public function testChangingTheDateResetsAnswersButOtherConcertEditsDoNot(): void
    {
        $id = $this->concert()->getId();
        $this->reply($id, ['status' => 'available', 'comment' => 'Ancienne précision.']);
        $crawler = $this->client->request('GET', '/concerts/'.$id.'/edit');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => ['city' => 'Paris'],
        ]);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            AvailabilityStatus::Available,
            $em
                ->getRepository(ConcertAvailability::class)
                ->findOneBy(['concert' => $id])
                ->getStatus(),
        );
        $crawler = $this->client->request('GET', '/concerts/'.$id.'/edit');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => ['date' => '2026-12-13'],
        ]);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'chaque responsable doit confirmer');
        $em = $this->entityManager();
        $em->clear();
        $record = $em->getRepository(ConcertAvailability::class)->findOneBy(['concert' => $id]);
        self::assertSame(AvailabilityStatus::Pending, $record->getStatus());
        self::assertNull($record->getComment());
        self::assertNull($record->getAnsweredAt());
    }

    public function testAnOldDateFormCannotConfirmAvailabilityForTheNewDate(): void
    {
        $id = $this->concert()->getId();
        $crawler = $this->client->request('GET', '/concerts/'.$id);
        $values = $crawler->filter('form[name="concert_availability"]')->form()->getPhpValues();
        $values['concert_availability']['status'] = 'available';
        $concert = $this->entityManager()->find(Concert::class, $id);
        $concert->updateDetails(
            new \DateTimeImmutable('2026-12-20'),
            $concert->getVenue(),
            $concert->getCity(),
            $concert->getStatus(),
        );
        $this->entityManager()->flush();
        $this->client->request('POST', '/concerts/'.$id.'/disponibilite', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="concert_availability"]', 'La date a changé');
        self::assertSame(
            AvailabilityStatus::Pending,
            $this->entityManager()
                ->getRepository(ConcertAvailability::class)
                ->findOneBy(['concert' => $id])
                ->getStatus(),
        );
    }

    #[DataProvider('readOnlyRoles')]
    public function testOtherRolesCanReadButCannotReplyForAManager(BandRole $role): void
    {
        $id = $this->concert()->getId();
        $user = $this->person($role->value, $role->label(), $role);
        $this->client->loginUser($user);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.availability-roster');
        self::assertSelectorNotExists('form[name="concert_availability"]');
        $this->client->request('POST', '/concerts/'.$id.'/disponibilite', [
            'concert_availability' => ['status' => 'available', 'user' => $this->userId],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public static function readOnlyRoles(): iterable
    {
        yield [BandRole::Booker];
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
    }

    public function testMissingCsrfInvalidChoiceOverlongCommentAndForgedIdentityAreRejected(): void
    {
        $id = $this->concert()->getId();
        $this->client->request('POST', '/concerts/'.$id.'/disponibilite', [
            'concert_availability' => ['status' => 'available', 'expectedDate' => '2026-12-12'],
        ]);
        self::assertResponseStatusCodeSame(422);
        foreach (
            [['status' => 'invented'], ['comment' => str_repeat('a', 501)], ['user' => 999]] as $changes
        ) {
            $this->reply($id, $changes);
            self::assertResponseStatusCodeSame(422);
        }
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            AvailabilityStatus::Pending,
            $em
                ->getRepository(ConcertAvailability::class)
                ->findOneBy(['concert' => $id])
                ->getStatus(),
        );
    }

    public function testAnotherBandCannotReadOrAnswerAvailability(): void
    {
        $id = $this->concert()->getId();
        $otherSheet = new \App\Entity\TechnicalSheet()->setBandName('Autre groupe');
        $otherBand = new Band('Autre groupe', $otherSheet);
        $em = $this->entityManager();
        $em->persist($otherBand);
        $em->flush();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'outsider@team.test',
                'Autre personne',
                'Test password 2026!',
                $otherBand,
                BandRole::Manager,
            );
        $this->client->loginUser($user);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/concerts/'.$id.'/disponibilite');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAManagerAddedLaterCanAnswerAnExistingConcert(): void
    {
        $id = $this->concert()->getId();
        $new = $this->person('new-engineer', 'Ingé son invité');
        $this->client->loginUser($new);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorTextContains('.availability-counter', '0 / 2');
        $this->reply($id, ['status' => 'available']);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            AvailabilityStatus::Available,
            $em
                ->getRepository(ConcertAvailability::class)
                ->findOneBy(['concert' => $id, 'user' => $new->getId()])
                ->getStatus(),
        );
        self::assertSame(
            2,
            $em->getRepository(ConcertAvailability::class)->count(['concert' => $id]),
        );
    }

    public function testTheBookerIsRecordedAsCreatorWithoutBeingAddedToThePerformingTeam(): void
    {
        $booker = $this->person('romain', 'Romain', BandRole::Booker);
        $bookerId = $booker->getId();
        $this->client->loginUser($booker);
        $crawler = $this->client->request('GET', '/concerts/new');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => [
                'date' => '2026-12-12',
                'venue' => 'Tournée',
                'city' => 'Paris',
                'status' => 'validated',
            ],
        ]);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $concert = $em->getRepository(Concert::class)->findOneBy([]);
        self::assertSame($bookerId, $concert->getCreatedBy()->getId());
        self::assertSame('Romain', $concert->getCreatedByName());
        self::assertSame(
            0,
            $em
                ->getRepository(ConcertAvailability::class)
                ->count(['concert' => $concert, 'user' => $bookerId]),
        );
        $this->client->followRedirect();
        self::assertSelectorTextContains('.concert-creator', 'Romain');
        self::assertSelectorNotExists('form[name="concert_availability"]');
    }

    private function person(string $key, string $name, BandRole $role = BandRole::Manager): User
    {
        return static::getContainer()
            ->get(UserCreator::class)
            ->create(
                $key.'@team.test',
                $name,
                'Team test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                $role,
            );
    }

    private function concert(): Concert
    {
        return static::getContainer()
            ->get(ConcertCreator::class)
            ->create($this->sheet(), new \DateTimeImmutable('2026-12-12'), 'Salle de test', 'Lyon');
    }

    private function reply(int $id, array $changes): void
    {
        $crawler = $this->client->request('GET', '/concerts/'.$id);
        $values = $crawler->filter('form[name="concert_availability"]')->form()->getPhpValues();
        $values['concert_availability'] = array_replace($values['concert_availability'], $changes);
        $this->client->request('POST', '/concerts/'.$id.'/disponibilite', $values);
    }
}
