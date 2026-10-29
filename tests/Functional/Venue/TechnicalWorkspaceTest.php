<?php

declare(strict_types=1);

namespace App\Tests\Functional\Venue;

use App\Entity\{Band, Concert, TechnicalSheet, User, VenueInvitation};
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TechnicalWorkspaceTest extends VenueTestCase
{
    public function testGroupedEquipmentDoesNotReorderAnUntouchedListOrOutdateItsPublication(): void
    {
        $concert = $this->concert();
        $sheet = $concert->getPatchSheet();
        $equipment = $sheet->getEquipmentRequests();
        $equipment[0]['category'] = 'Autre';
        $equipment[array_key_last($equipment)]['category'] = 'Autre';
        $sheet->setEquipmentRequests($equipment);
        $this->entityManager()->flush();
        $issued = $this->issue($concert);
        $publicationId = $issued->invitation->getPublication()->getId();
        $sheetId = $sheet->getId();
        $crawler = $this->client->request('GET', '/fiches/' . $sheetId . '/materiel');
        self::assertCount(1, $crawler->filter('[data-equipment-group][data-category="Autre"]'));
        self::assertCount(2, $crawler->filter('[data-category="Autre"] [data-collection-item]'));
        $this->client->submit($crawler->filter('form[name="equipment"]')->form());
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            $equipment,
            $em->find(TechnicalSheet::class, $sheetId)->getEquipmentRequests(),
        );
        $publication = $em->find(\App\Entity\TechnicalPublication::class, $publicationId);
        self::assertFalse(
            static::getContainer()
                ->get(\App\Service\Venue\PublicationBuilder::class)
                ->isOutdated($publication),
        );
    }

    public function testManagerCanCreateAndRevokeAnInvitationWithCsrf(): void
    {
        $id = $this->concert()->getId();
        $crawler = $this->client->request('GET', '/concerts/' . $id . '/invitations');
        $this->client->submit($crawler->filter('form[name="invitation"]')->form(), [
            'invitation' => ['label' => 'Régie du lieu', 'days' => '7'],
        ]);
        self::assertResponseRedirects('/concerts/' . $id . '/invitations', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('#invitation-link');
        $invitation = $this->entityManager()->getRepository(VenueInvitation::class)->findOneBy([]);
        $revoke = $crawler
            ->filter('form[action="/invitations/' . $invitation->getId() . '/revoquer"]')
            ->form();
        $this->client->request('POST', '/invitations/' . $invitation->getId() . '/revoquer', [
            '_token' => 'invalid',
        ]);
        self::assertResponseStatusCodeSame(403);
        $this->client->submit($revoke);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertFalse($em->find(VenueInvitation::class, $invitation->getId())->isUsable());
    }

    public function testPublishingRequiresCsrfAndDoesNotExposeTheTokenAgain(): void
    {
        $id = $this->concert()->getId();
        $this->client->request('POST', '/concerts/' . $id . '/invitations/creer', [
            'invitation' => ['label' => 'Régie', 'days' => '30'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            0,
            $this->entityManager()->getRepository(VenueInvitation::class)->count([]),
        );
        $crawler = $this->client->request('GET', '/concerts/' . $id . '/invitations');
        $this->client->submit($crawler->filter('form[name="invitation"]')->form(), [
            'invitation' => ['label' => 'Régie'],
        ]);
        $this->client->followRedirect();
        self::assertSelectorExists('#invitation-link');
        $this->client->request('GET', '/concerts/' . $id . '/invitations');
        self::assertSelectorNotExists('#invitation-link');
    }

    #[DataProvider('limitedRoles')]
    public function testOnlyManagersEditThePlanEquipmentAndInvitations(BandRole $role): void
    {
        $concert = $this->concert();
        $concertId = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $issued = $this->issue($concert);
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'limited@example.test',
                'Profil limité',
                'Limited test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                $role,
            );
        $this->client->loginUser($user);
        foreach (
            [
                '/fiches/' . $sheetId . '/plan-de-scene',
                '/fiches/' . $sheetId . '/materiel',
                '/concerts/' . $concertId . '/invitations',
            ]
            as $uri
        ) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
        }
        foreach (
            [
                '/fiches/' . $sheetId . '/plan-de-scene',
                '/fiches/' . $sheetId . '/materiel',
                '/concerts/' . $concertId . '/invitations/creer',
                '/invitations/' . $issued->invitation->getId() . '/revoquer',
            ]
            as $uri
        ) {
            $this->client->request('POST', $uri);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET', '/concerts/' . $concertId . '/reponses-salle');
        self::assertResponseIsSuccessful();
    }

    public static function limitedRoles(): iterable
    {
        yield [BandRole::Booker];
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
    }

    public function testPlanAndEquipmentChangesAreSavedOnlyForTheirSheet(): void
    {
        $concert = $this->concert();
        $sheetId = $concert->getPatchSheet()->getId();
        $crawler = $this->client->request('GET', '/fiches/' . $sheetId . '/plan-de-scene');
        $this->client->submit($crawler->filter('form[name="stage_plan"]')->form(), [
            'stage_plan' => [
                'items' => [1 => ['x' => '60', 'notes' => 'IEM filaire stéréo pour cette date.']],
            ],
        ]);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->request('GET', '/fiches/' . $sheetId . '/materiel');
        $values = $crawler->filter('form[name="equipment"]')->form()->getPhpValues();
        $values['equipment']['items'][0]['quantity'] = '2';
        $values['equipment']['items'][] = [
            'id' => '',
            'category' => 'Autre',
            'label' => 'Cab supplémentaire',
            'quantity' => '1',
            'specification' => 'À convenir',
        ];
        $this->client->request('POST', '/fiches/' . $sheetId . '/materiel', $values);
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $copy = $em->find(TechnicalSheet::class, $sheetId);
        self::assertSame(60.0, (float) $copy->getStagePlan()[1]['x']);
        self::assertSame(35.0, (float) $this->sheet()->getStagePlan()[1]['x']);
        self::assertCount(20, $copy->getEquipmentRequests());
        self::assertCount(19, $this->sheet()->getEquipmentRequests());
        self::assertNotEmpty($copy->getEquipmentRequests()[19]['id']);
    }

    public function testInvalidCoordinatesMissingTypeAndMissingCsrfCannotChangeThePlan(): void
    {
        $uri = '/fiches/' . $this->sheetId . '/plan-de-scene';
        $crawler = $this->client->request('GET', $uri);
        $original = $this->sheet()->getStagePlan();
        $values = $crawler->filter('form[name="stage_plan"]')->form()->getPhpValues();
        $invalid = $values;
        $invalid['stage_plan']['items'][0]['x'] = '101';
        $this->client->request('POST', $uri, $invalid);
        self::assertResponseStatusCodeSame(422);
        $invalid = $values;
        unset($invalid['stage_plan']['items'][0]['kind']);
        $this->client->request('POST', $uri, $invalid);
        self::assertResponseStatusCodeSame(422);
        $invalid = $values;
        unset($invalid['stage_plan']['_token']);
        $this->client->request('POST', $uri, $invalid);
        self::assertResponseStatusCodeSame(422);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame($original, $this->sheet()->getStagePlan());
    }

    public function testVisualTransformsAreStoredAndIncludedInTheGuestPublication(): void
    {
        $concert = $this->concert();
        $concertId = $concert->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $crawler = $this->client->request('GET', '/fiches/' . $sheetId . '/plan-de-scene');
        $values = $crawler->filter('form[name="stage_plan"]')->form()->getPhpValues();
        $values['stage_plan']['items'][2]['rotation'] = '90';
        $values['stage_plan']['items'][2]['scale'] = '1.5';
        $this->client->request('POST', '/fiches/' . $sheetId . '/plan-de-scene', $values);
        self::assertResponseStatusCodeSame(303);

        $em = $this->entityManager();
        $em->clear();
        $concert = $em->find(Concert::class, $concertId);
        $item = $concert->getPatchSheet()->getStagePlan()[2];
        self::assertSame(90.0, (float) $item['rotation']);
        self::assertSame(1.5, (float) $item['scale']);
        self::assertArrayNotHasKey('rotation', $this->sheet()->getStagePlan()[2]);

        $this->enter($this->issue($concert)->token);
        self::assertSelectorExists(
            '.stage-amp[style*="--stage-rotation: 90.0deg"][style*="--stage-scale: 1.50"]',
        );
    }

    public function testSavingAnUntouchedLegacyPlanKeepsItsPublicationCurrent(): void
    {
        $concert = $this->concert();
        $issued = $this->issue($concert);
        $publicationId = $issued->invitation->getPublication()->getId();
        $sheetId = $concert->getPatchSheet()->getId();
        $crawler = $this->client->request('GET', '/fiches/' . $sheetId . '/plan-de-scene');
        $this->client->submit($crawler->filter('form[name="stage_plan"]')->form());
        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $publication = $em->find(\App\Entity\TechnicalPublication::class, $publicationId);
        self::assertFalse(
            static::getContainer()
                ->get(\App\Service\Venue\PublicationBuilder::class)
                ->isOutdated($publication),
        );
    }

    #[DataProvider('invalidTransforms')]
    public function testForgedTransformsCannotChangeThePlan(string $field, string $value): void
    {
        $uri = '/fiches/' . $this->sheetId . '/plan-de-scene';
        $crawler = $this->client->request('GET', $uri);
        $original = $this->sheet()->getStagePlan();
        $values = $crawler->filter('form[name="stage_plan"]')->form()->getPhpValues();
        $values['stage_plan']['items'][2][$field] = $value;
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Le plan n’a pas été enregistré.');
        $em = $this->entityManager();
        $em->clear();
        self::assertSame($original, $this->sheet()->getStagePlan());
    }

    public static function invalidTransforms(): iterable
    {
        yield ['rotation', '361'];
        yield ['rotation', '-1'];
        yield ['rotation', ''];
        yield ['scale', '0.49'];
        yield ['scale', '2.51'];
        yield ['scale', 'NaN'];
    }

    public function testStalePlanAndOtherGroupsSheetsAreProtected(): void
    {
        $uri = '/fiches/' . $this->sheetId . '/plan-de-scene';
        $crawler = $this->client->request('GET', $uri);
        $values = $crawler->filter('form[name="stage_plan"]')->form()->getPhpValues();
        $plan = $this->sheet()->getStagePlan();
        $plan[0]['label'] = 'Nouveau praticable';
        $this->sheet()->setStagePlan($plan);
        $this->entityManager()->flush();
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(409);
        $sheet = new TechnicalSheet()->setBandName('Autre groupe');
        $this->entityManager()->persist(new Band('Autre groupe', $sheet));
        $this->entityManager()->flush();
        $id = $sheet->getId();
        foreach (['plan-de-scene', 'materiel'] as $suffix) {
            foreach (['GET', 'POST'] as $method) {
                $this->client->request($method, '/fiches/' . $id . '/' . $suffix);
                self::assertResponseStatusCodeSame(403);
            }
        }
    }
}
