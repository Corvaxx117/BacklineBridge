<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert\Import;

use App\Entity\Band;
use App\Entity\BandsintownConcert;
use App\Entity\ConcertConditions;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\ConcertStatus;
use App\Enum\EngineerPresence;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImportedConcertConditionsTest extends DatabaseTestCase
{
    private int $eventId = 7000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
    }

    public function testAllConcertsExposeEngineerPresenceWithoutMemberAvailability(): void
    {
        $importedId = $this->importedConcert();
        $manual = static::getContainer()->get(ConcertCreator::class)->create(
            $this->sheet(), new \DateTimeImmutable('2026-12-13'), 'Salle manuelle', 'Lyon',
        );
        foreach ([$importedId, $manual->getId()] as $id) {
            $this->client->request('GET', '/concerts/'.$id);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('#disponibilites');
            self::assertSelectorTextContains('[data-engineer-presence]', 'À confirmer');
            $this->client->request('POST', '/concerts/'.$id.'/disponibilite');
            self::assertResponseStatusCodeSame(404);
            $this->client->request('GET', '/concerts/'.$id.'/conditions');
            self::assertSelectorExists('[name="concert_conditions[engineerPresence]"]');
        }
        $this->client->request('GET', '/concerts');
        self::assertSelectorNotExists('.concert-availability-link');
    }

    #[DataProvider('organizers')]
    public function testOrganizersCanSavePresenceForOneImportedConcert(BandRole $role): void
    {
        $first = $this->importedConcert();
        $second = $this->importedConcert('Autre salle');
        if (BandRole::Manager !== $role) {
            $this->loginAs($role);
        }
        foreach (['present' => 'Présent', 'absent' => 'Absent', 'unknown' => 'À confirmer'] as $value => $label) {
            $this->submitConditions($first, ['engineerPresence' => $value, 'feeAmount' => '1500,50']);
            self::assertResponseRedirects('/concerts/'.$first.'#concert-conditions', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('[data-engineer-presence]', $label);
            self::assertSelectorNotExists('#disponibilites');
            $em = $this->entityManager();
            $em->clear();
            $conditions = $em->getRepository(ConcertConditions::class)->findOneBy(['concert' => $first]);
            self::assertInstanceOf(ConcertConditions::class, $conditions);
            self::assertSame(EngineerPresence::from($value), $conditions->getEngineerPresence());
            self::assertSame(150050, $conditions->getFeeCents());
            self::assertSame('unknown', $conditions->getEngineerPayment()->value);
            self::assertNull($em->getRepository(ConcertConditions::class)->findOneBy(['concert' => $second]));
        }
    }

    public static function organizers(): iterable
    {
        yield 'responsable' => [BandRole::Manager];
        yield 'booker' => [BandRole::Booker];
        yield 'manager' => [BandRole::TeamManager];
    }

    public function testInvalidChoicesAndMissingCsrfCannotOverwritePresence(): void
    {
        $id = $this->importedConcert();
        $this->submitConditions($id, ['engineerPresence' => 'present']);
        self::assertResponseStatusCodeSame(303);
        foreach ([['engineerPresence' => 'invented'], ['engineerPresence' => '']] as $changes) {
            $this->submitConditions($id, $changes);
            self::assertResponseStatusCodeSame(422);
        }
        $this->submitConditions($id, ['engineerPresence' => 'absent'], false);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorTextContains('[data-engineer-presence]', 'Présent');
    }

    public function testChangingTheDateRequiresEngineerConfirmationAndRejectsTheOldForm(): void
    {
        $id = $this->importedConcert();
        $this->submitConditions($id, ['engineerPresence' => 'present', 'feeAmount' => '1500']);
        $old = $this->client->request('GET', '/concerts/'.$id.'/conditions')
            ->filter('form[name="concert_conditions"]')->form()->getPhpValues();
        $edit = $this->client->request('GET', '/concerts/'.$id.'/edit');
        self::assertSelectorTextContains('.form-aside', 'présence de l’ingé son');
        $this->client->submit($edit->filter('form[name="concert"]')->form(), [
            'concert' => ['date' => '2026-12-14'],
        ]);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'la présence de l’ingé son est à confirmer');
        self::assertSelectorTextContains('[data-engineer-presence]', 'À confirmer');
        self::assertSelectorTextContains('.conditions-overview', '1 500,00 €');
        self::assertSelectorNotExists('#disponibilites');
        $this->client->request('POST', '/concerts/'.$id.'/conditions', $old);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error-summary', 'La date a changé');
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorTextContains('[data-engineer-presence]', 'À confirmer');
    }

    public function testReadersAndOtherBandsCannotChangePresence(): void
    {
        $id = $this->importedConcert();
        $this->loginAs(BandRole::Viewer);
        $this->client->request('GET', '/concerts/'.$id.'/conditions');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/concerts/'.$id.'/conditions');
        self::assertResponseStatusCodeSame(403);
        $em = $this->entityManager();
        $other = new Band('Autre groupe', new TechnicalSheet()->setBandName('Autre groupe'));
        $em->persist($other);
        $em->flush();
        $user = static::getContainer()->get(UserCreator::class)->create(
            'other-organizer@example.test', 'Autre responsable', 'OtherTest2026!', $other, BandRole::Manager,
        );
        $this->client->loginUser($user);
        $this->client->request('POST', '/concerts/'.$id.'/conditions');
        self::assertResponseStatusCodeSame(403);
    }

    private function importedConcert(string $venue = 'Salle importée'): int
    {
        $em = $this->entityManager();
        $concert = static::getContainer()->get(ConcertCreator::class)->prepare(
            $this->sheet(), new \DateTimeImmutable('2026-12-12'), $venue, 'Lyon', ConcertStatus::Validated,
        );
        $event = new BandsintownEvent(
            (string) ++$this->eventId, '15466975', '2026-12-12', '20:30', $venue, 'Lyon',
            'France', '', '', '', ['Avee Mana'], [],
        );
        $em->persist(new BandsintownConcert($concert, $event));
        $em->flush();

        return $concert->getId();
    }

    private function loginAs(BandRole $role): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $user = static::getContainer()->get(UserCreator::class)->create(
            $role->value.'@example.test', $role->label(), 'RoleTest2026!', $band, $role,
        );
        $this->client->loginUser($user);
    }

    /** @param array<string, string> $changes */
    private function submitConditions(int $id, array $changes, bool $withCsrf = true): void
    {
        $form = $this->client->request('GET', '/concerts/'.$id.'/conditions')
            ->filter('form[name="concert_conditions"]')->form();
        $values = $form->getPhpValues();
        $values['concert_conditions'] = array_replace($values['concert_conditions'], $changes);
        if (!$withCsrf) {
            unset($values['concert_conditions']['_token']);
        }
        $this->client->request('POST', $form->getUri(), $values);
    }
}
