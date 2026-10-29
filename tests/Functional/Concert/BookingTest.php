<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Enum\BandRole;
use App\Enum\ConcertStatus;
use App\Service\Concert\ConcertCreator;
use App\Service\Concert\ConcertConditionsUpdater;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BookingTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
    }

    public function testBookerCanCreateValidateAndUpdateAConcertWithoutEditingItsPatch(): void
    {
        $this->loginAs(BandRole::Booker);
        $crawler = $this->client->request('GET', '/concerts/new');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => [
                'date' => '2026-12-15',
                'venue' => 'Salle réservée',
                'city' => 'Lyon',
                'status' => 'validated',
                'notes' => 'Repas prévu sur place.',
            ],
        ]);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.status-badge', 'Validé');
        self::assertSelectorTextContains('.role-badge', 'Tourneur');
        self::assertSelectorExists('.technical-dossier:not([open])');
        self::assertSelectorNotExists('.patch-table a.edit-link');
        self::assertSelectorTextContains('.concert-notes', 'Repas prévu sur place.');
        self::assertSelectorNotExists('.delete-source');
        $concert = $this->entityManager()->getRepository(Concert::class)->findOneBy([]);
        $id = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $sourceId = $concert->getPatchSheet()->getLines()->first()->getId();
        $crawler = $this->client->request('GET', '/concerts/' . $id . '/edit');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => ['city' => 'Marseille', 'status' => 'pending'],
        ]);
        self::assertResponseRedirects('/concerts/' . $id, 303);
        $em = $this->entityManager();
        $em->clear();
        $concert = $em->find(Concert::class, $id);
        self::assertSame(ConcertStatus::Pending, $concert->getStatus());
        self::assertSame('Marseille', $concert->getCity());
        self::assertSame('Repas prévu sur place.', $concert->getNotes());
        self::assertSame($sheetId, $concert->getPatchSheet()->getId());
        $this->client->request('POST', '/sources/' . $sourceId . '/edit', [
            'patch_line' => ['notes' => 'Interdit'],
        ]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/concerts/' . $id . '/sources/new');
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('organizingRoles')]
    public function testOrganizersCanUpdateAndClearNotesForOneConcert(BandRole $role): void
    {
        $first = $this->concert();
        $firstId = $first->getId();
        $second = static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $this->sheet(),
                new \DateTimeImmutable('2026-12-13'),
                'Autre salle',
                'Paris',
                notes: 'Repas à 18 h.',
            );
        $secondId = $second->getId();
        if ($role === BandRole::Booker) {
            $this->loginAs($role);
        }

        $crawler = $this->client->request('GET', '/concerts/' . $firstId . '/edit');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => ['notes' => "  Hébergement à proximité.\nDépart à 10 h.  "],
        ]);
        self::assertResponseRedirects('/concerts/' . $firstId, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.concert-notes', 'Hébergement à proximité.');

        $crawler = $this->client->request('GET', '/concerts/' . $firstId . '/edit');
        $form = $crawler->filter('form[name="concert"]')->form();
        self::assertSame(
            "Hébergement à proximité.\nDépart à 10 h.",
            $form->getPhpValues()['concert']['notes'],
        );
        $this->client->submit($form, ['concert' => ['notes' => "  \n  "]]);
        self::assertResponseRedirects('/concerts/' . $firstId, 303);
        $this->client->followRedirect();
        self::assertSelectorNotExists('.concert-notes');

        $em = $this->entityManager();
        $em->clear();
        self::assertNull($em->find(Concert::class, $firstId)->getNotes());
        self::assertSame('Repas à 18 h.', $em->find(Concert::class, $secondId)->getNotes());
    }

    public static function organizingRoles(): iterable
    {
        yield [BandRole::Manager];
        yield [BandRole::Booker];
    }

    public function testOverlongNotesCannotOverwriteExistingNotes(): void
    {
        $id = $this->concert()->getId();
        $crawler = $this->client->request('GET', '/concerts/' . $id . '/edit');
        $this->client->submit($crawler->filter('form[name="concert"]')->form(), [
            'concert' => ['notes' => 'Repas à 19 h.'],
        ]);
        self::assertResponseStatusCodeSame(303);

        $crawler = $this->client->request('GET', '/concerts/' . $id . '/edit');
        $values = $crawler->filter('form[name="concert"]')->form()->getPhpValues();
        $values['concert']['notes'] = str_repeat('é', 2001);
        $this->client->request('POST', '/concerts/' . $id . '/edit', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#concert_notes_errors', '2000 caractères');
        $em = $this->entityManager();
        $em->clear();
        self::assertSame('Repas à 19 h.', $em->find(Concert::class, $id)->getNotes());
    }

    public function testBookerCanRecordConditionsWithExactAmountsAndSeparateEngineerPayment(): void
    {
        $concert = $this->concert();
        $id = $concert->getId();
        $this->loginAs(BandRole::Booker);
        $this->submitConditions($id, [
            'accommodation' => 'on_site',
            'transportCoverage' => 'covered',
            'paymentFramework' => 'artist_fees',
            'paymentMethod' => 'bank_transfer',
            'engineerPayment' => 'separate',
            'engineerPaymentFramework' => 'artist_fees',
            'feeAmount' => '1500,50',
            'engineerFeeAmount' => '250.05',
            'notes' => 'Hébergement pour cinq personnes.',
        ]);
        self::assertResponseRedirects('/concerts/' . $id, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.conditions-panel', '1 500,50 €');
        self::assertSelectorTextContains('.conditions-panel', '250,05 €');
        $em = $this->entityManager();
        $em->clear();
        $conditions = $em->getRepository(ConcertConditions::class)->findOneBy(['concert' => $id]);
        self::assertSame(150050, $conditions->getFeeCents());
        self::assertSame(25005, $conditions->getEngineerFeeCents());
        self::assertSame('on_site', $conditions->getAccommodation()->value);
        self::assertSame('separate', $conditions->getEngineerPayment()->value);
        self::assertSame('artist_fees', $conditions->getEngineerPaymentFramework()->value);
        $this->submitConditions($id, [
            'feeAmount' => '0',
            'engineerFeeAmount' => '',
            'accommodation' => 'return_home',
        ]);
        self::assertResponseRedirects('/concerts/' . $id, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.conditions-panel', 'Retour à la casa !');
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            0,
            $em
                ->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $id])
                ->getFeeCents(),
        );
        self::assertNull(
            $em
                ->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $id])
                ->getEngineerFeeCents(),
        );
        self::assertSame(
            'return_home',
            $em
                ->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $id])
                ->getAccommodation()->value,
        );
        self::assertSame(1, $em->getRepository(ConcertConditions::class)->count([]));
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountsAreRejectedWithoutRecordingConditions(string $amount): void
    {
        $id = $this->concert()->getId();
        $this->submitConditions($id, ['feeAmount' => $amount]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            0,
            $this->entityManager()->getRepository(ConcertConditions::class)->count([]),
        );
    }

    public static function invalidAmounts(): iterable
    {
        yield ['-20'];
        yield ['12,345'];
        yield ['1e3'];
        yield ['inconnu'];
        yield ['99999999'];
    }

    #[DataProvider('technicalRoles')]
    public function testTechnicalProfilesCannotReadOrModifyCommercialConditions(
        BandRole $role,
    ): void {
        $concert = $this->concert();
        $id = $concert->getId();
        $concert->updateDetails(
            $concert->getDate(),
            $concert->getVenue(),
            $concert->getCity(),
            $concert->getStatus(),
            'Repas à 19 h.',
        );
        $input = new ConcertConditionsInput();
        $input->feeAmount = '9876.54';
        $input->notes = 'ACCORD FINANCIER CONFIDENTIEL';
        static::getContainer()->get(ConcertConditionsUpdater::class)->save($concert, $input);
        $this->loginAs($role);
        $this->client->request('GET', '/concerts/' . $id);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.conditions-panel');
        self::assertSelectorTextContains('.concert-notes', 'Repas à 19 h.');
        self::assertSelectorNotExists('.concert-notes a');
        self::assertSelectorTextNotContains('main', 'ACCORD FINANCIER CONFIDENTIEL');
        self::assertSelectorTextNotContains('main', '9 876,54');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/concerts/' . $id . '/conditions');
            self::assertResponseStatusCodeSame(403);
            $this->client->request($method, '/concerts/' . $id . '/edit');
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/concerts/' . $id . '/edit', [
            'concert' => ['notes' => 'MODIFICATION INTERDITE'],
        ]);
        self::assertResponseStatusCodeSame(403);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame('Repas à 19 h.', $em->find(Concert::class, $id)->getNotes());
    }

    public static function technicalRoles(): iterable
    {
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
    }

    public function testConditionsAndConcertChangesRequireCsrf(): void
    {
        $id = $this->concert()->getId();
        $this->client->request('POST', '/concerts/' . $id . '/conditions', [
            'concert_conditions' => ['feeAmount' => '100'],
        ]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/concerts/' . $id . '/edit', [
            'concert' => [
                'date' => '2026-12-12',
                'venue' => 'Changé',
                'city' => 'Lyon',
                'status' => 'validated',
                'notes' => 'MODIFICATION SANS CSRF',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(0, $em->getRepository(ConcertConditions::class)->count([]));
        self::assertSame(ConcertStatus::Pending, $em->find(Concert::class, $id)->getStatus());
        self::assertSame('Salle de test', $em->find(Concert::class, $id)->getVenue());
        self::assertNull($em->find(Concert::class, $id)->getNotes());
    }

    private function concert(): Concert
    {
        return static::getContainer()
            ->get(ConcertCreator::class)
            ->create($this->sheet(), new \DateTimeImmutable('2026-12-12'), 'Salle de test', 'Lyon');
    }

    public function testMissingOrUnknownChoicesWithAValidCsrfReturnAValidationError(): void
    {
        $id = $this->concert()->getId();
        $crawler = $this->client->request('GET', '/concerts/' . $id . '/conditions');
        $values = $crawler->filter('form[name="concert_conditions"]')->form()->getPhpValues();
        $values['concert_conditions']['accommodation'] = 'not_an_option';
        $this->client->request('POST', '/concerts/' . $id . '/conditions', $values);
        self::assertResponseStatusCodeSame(422);
        unset($values['concert_conditions']['accommodation']);
        $this->client->request('POST', '/concerts/' . $id . '/conditions', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            0,
            $this->entityManager()->getRepository(ConcertConditions::class)->count([]),
        );
    }

    private function loginAs(BandRole $role): void
    {
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'profile@example.test',
                'Profil ' . $role->value,
                'Profile test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                $role,
            );
        $this->client->loginUser($user);
    }

    private function submitConditions(int $id, array $changes): void
    {
        $crawler = $this->client->request('GET', '/concerts/' . $id . '/conditions');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="concert_conditions"]')->form(), [
            'concert_conditions' => $changes,
        ]);
    }
}
