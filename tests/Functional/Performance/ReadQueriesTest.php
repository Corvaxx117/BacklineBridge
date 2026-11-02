<?php

declare(strict_types=1);

namespace App\Tests\Functional\Performance;

use App\Dto\Venue\InvitationInput;
use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\BandTask;
use App\Entity\Concert;
use App\Entity\MemberProfile;
use App\Entity\User;
use App\Enum\DocumentCategory;
use App\Enum\MemberKind;
use App\Enum\TaskStatus;
use App\Service\Concert\ConcertCreator;
use App\Service\Venue\InvitationIssuer;
use App\Tests\DatabaseTestCase;
use App\Tests\Support\QueryCounter;

final class ReadQueriesTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $em = $this->entityManager();
        $em->persist(new MemberProfile($em->find(Band::class, $this->bandId), $em->find(User::class, $this->userId), 'Musicien fictif', 'Guitare', MemberKind::Musician));
        $em->flush();
    }

    public function testConcertListQueryCountDoesNotGrowWithTheNumberOfDates(): void
    {
        $before = $this->queries('/concerts');
        $creator = static::getContainer()->get(ConcertCreator::class);
        $sheet = $this->sheet();
        for ($i = 0; $i < 50; ++$i) {
            $creator->prepare($sheet, new \DateTimeImmutable('+'.($i + 1).' days'), 'Salle '.$i, 'Ville');
        }
        $this->entityManager()->flush();
        $after = $this->queries('/concerts');
        self::assertSelectorCount(50, '.concert-card');
        self::assertLessThanOrEqual(20, $after);
        self::assertLessThanOrEqual($before + 1, $after);
    }

    public function testHundredDocumentsAndTasksUseBoundedPermissionQueries(): void
    {
        $em = $this->entityManager();
        $band = $em->find(Band::class, $this->bandId);
        $user = $em->find(User::class, $this->userId);
        for ($i = 0; $i < 100; ++$i) {
            $em->persist(new BandDocument($band, null, $user, 'Document '.$i, DocumentCategory::Technical, null, 'https://example.test/'.$i, null, null, 0));
            $task = new BandTask($band, $user);
            $task->update('Tâche '.$i, '', null, null, TaskStatus::Todo);
            $em->persist($task);
        }
        $em->flush();
        self::assertLessThanOrEqual(15, $this->queries('/groupes/'.$this->bandId.'/documents'));
        self::assertLessThanOrEqual(15, $this->queries('/groupes/'.$this->bandId.'/taches'));
    }

    public function testConcertDetailAndInvitationsUseGroupedReads(): void
    {
        $concert = static::getContainer()->get(ConcertCreator::class)->create($this->sheet(), new \DateTimeImmutable('+3 days'), 'Salle', 'Ville');
        self::assertLessThanOrEqual(20, $this->queries('/concerts/'.$concert->getId()));
        $concert = $this->entityManager()->find(Concert::class, $concert->getId());
        $issuer = static::getContainer()->get(InvitationIssuer::class);
        for ($i = 0; $i < 20; ++$i) {
            $input = new InvitationInput();
            $input->label = 'Salle '.$i;
            $issuer->issue($concert, $input);
        }
        self::assertLessThanOrEqual(20, $this->queries('/concerts/'.$concert->getId().'/invitations'));
    }

    private function queries(string $url): int
    {
        $this->entityManager()->clear();
        static::getContainer()->get(QueryCounter::class)->reset();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return static::getContainer()->get(QueryCounter::class)->count();
    }
}
