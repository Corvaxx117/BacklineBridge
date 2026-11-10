<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Entity\OrfeoConcert;
use App\Enum\BandRole;
use App\Integration\Orfeo\Dto\OrfeoContract;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;

final class ConditionsTest extends VenueTestCase
{
    private function concertWithSource(): Concert
    {
        $concert = $this->concert();
        $event = new OrfeoEvent(901, 1001, 301, '2026-12-12', '20:00', 'Salle', 'Ville', null, 1, 'Confirmé', true, false, [], [
            new OrfeoContract(5, 'Contrat fictif', 'Confirmé', 'HEBERGEMENT PRIVE ORFEO', 'Train remboursé.', 'Repas sur place.', 150050, 180060),
        ]);
        $this->entityManager()->persist(new OrfeoConcert($concert, $event, new \DateTimeImmutable()));
        $this->entityManager()->flush();

        return $concert;
    }

    public function testReadingProposalsDoesNotChangeConditionsAndSavingIsExplicit(): void
    {
        $concert = $this->concertWithSource();
        $id = $concert->getId();
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#disponibilites');
        self::assertSelectorTextNotContains('main', 'HEBERGEMENT PRIVE ORFEO');
        $form = $this->client->request('GET', '/concerts/'.$id.'/conditions')->filter('form[name="concert_conditions"]')->form();
        self::assertSelectorTextContains('#orfeo-conditions', '1 500,50');
        self::assertSelectorTextContains('#orfeo-conditions', '1 800,60');
        self::assertSelectorTextSame('textarea[name="concert_conditions[accommodationNotes]"]', '');
        self::assertInputValueSame('concert_conditions[feeAmount]', '');
        self::assertNull($this->entityManager()->getRepository(ConcertConditions::class)->findOneBy(['concert' => $id]));
        $this->client->submit($form, ['concert_conditions' => [
            'accommodationNotes' => 'HEBERGEMENT PRIVE ORFEO', 'transportNotes' => 'Train remboursé.',
            'mealNotes' => 'Repas sur place.', 'feeAmount' => '1500.50', 'feeBasis' => 'ht',
        ]]);
        self::assertResponseRedirects('/concerts/'.$id.'#concert-conditions', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.conditions-overview', 'HEBERGEMENT PRIVE ORFEO');
        self::assertSelectorTextContains('.conditions-overview', '1 500,50 €');
        $conditions = $this->entityManager()->getRepository(ConcertConditions::class)->findOneBy(['concert' => $id]);
        self::assertSame('unknown', $conditions->getAccommodation()->value);
        self::assertSame('Repas sur place.', $conditions->getMealNotes());
        self::assertSame(150050, $conditions->getFeeCents());
        self::assertSame('ht', $conditions->getFeeBasis()->value);
    }

    public function testTwoEditorsCannotOverwriteEachOtherAndEmptyRevisionsAreRejected(): void
    {
        $id = $this->concert()->getId();
        $url = '/concerts/'.$id.'/conditions';
        $first = $this->client->request('GET', $url)->filter('form[name="concert_conditions"]')->form();
        $stale = $this->client->request('GET', $url)->filter('form[name="concert_conditions"]')->form();
        $this->client->submit($first, ['concert_conditions' => ['mealNotes' => 'Accord récent']]);
        self::assertResponseStatusCodeSame(303);
        $this->client->submit($stale, ['concert_conditions' => ['mealNotes' => 'Ancien accord']]);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextSame('textarea[name="concert_conditions[mealNotes]"]', 'Ancien accord');
        $values = $this->client->request('GET', $url)->filter('form[name="concert_conditions"]')->form()->getPhpValues();
        unset($values['concert_conditions']['revision']);
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        self::assertSame('Accord récent', $this->entityManager()->getRepository(ConcertConditions::class)->findOneBy(['concert' => $id])->getMealNotes());
    }

    public function testConditionsAreNotExposedToGuestsOrReadOnlyMembers(): void
    {
        $concert = $this->concertWithSource();
        $id = $concert->getId();
        $form = $this->client->request('GET', '/concerts/'.$id.'/conditions')->filter('form[name="concert_conditions"]')->form();
        $this->client->submit($form, ['concert_conditions' => ['accommodationNotes' => 'HEBERGEMENT PRIVE ORFEO']]);
        self::assertResponseStatusCodeSame(303);
        $issued = $this->issue($this->entityManager()->find(Concert::class, $id));
        $this->enter($issued->token);
        self::assertSelectorTextNotContains('main', 'HEBERGEMENT PRIVE ORFEO');
        self::assertSelectorNotExists('#concert-conditions');
        $user = static::getContainer()->get(UserCreator::class)->create('reader-conditions@example.test', 'Lecteur', 'CompteTest2026!', $this->entityManager()->find(Band::class, $this->bandId), BandRole::Viewer);
        $this->client->loginUser($user);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#concert-conditions');
        self::assertSelectorTextNotContains('main', 'HEBERGEMENT PRIVE ORFEO');
        $this->client->request('GET', '/concerts/'.$id.'/conditions');
        self::assertResponseStatusCodeSame(403);
    }
}
