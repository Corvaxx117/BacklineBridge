<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\ConcertContact;
use App\Enum\BandRole;
use App\Tests\CollaborationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ContactTest extends CollaborationTestCase
{
    public function testContactsCanBeAddedUpdatedAndRemovedFromOneConcert(): void
    {
        $concertId = $this->concert()->getId();
        $otherId = $this->concert()->getId();
        $form = $this->client->request('GET', '/concerts/'.$concertId.'/contacts/ajouter')->filter('form[name="contact"]')->form();
        $this->client->submit($form, ['contact' => [
            'name' => 'Camille',
            'role' => 'stage_manager',
            'email' => 'regie@example.test',
            'phone' => '+33 (0)6 12 34 56 78',
            'notes' => 'Joignable après 17 h.',
        ]]);
        self::assertResponseRedirects('/concerts/'.$concertId.'#concert-contacts', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#concert-contacts', 'Camille');
        self::assertSelectorExists('a[href="mailto:regie@example.test"]');
        self::assertSelectorExists('a[href="tel:+330612345678"]');
        $id = $this->entityManager()->getRepository(ConcertContact::class)->findOneBy([])->getId();
        $form = $this->client->request('GET', '/contacts-concert/'.$id.'/modifier')->filter('form[name="contact"]')->form();
        $this->client->submit($form, ['contact' => ['role' => 'friend_of_friend', 'phone' => '']]);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('#concert-contacts', 'Pote de pote');
        self::assertSelectorNotExists('#concert-contacts a[href^="tel:"]');
        $this->client->request('GET', '/concerts/'.$otherId);
        self::assertSelectorTextNotContains('#concert-contacts', 'Camille');
        $form = $crawler->filter('form[action="/contacts-concert/'.$id.'/supprimer"]')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->entityManager()->getRepository(ConcertContact::class)->count([]));
    }

    public function testInvalidContactAndMissingCsrfCannotBeSaved(): void
    {
        $id = $this->concert()->getId();
        $uri = '/concerts/'.$id.'/contacts/ajouter';
        $form = $this->client->request('GET', $uri)->filter('form[name="contact"]')->form();
        $values = $form->getPhpValues();
        $values['contact']['name'] = 'Camille';
        $values['contact']['email'] = 'not-an-email';
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $values['contact']['email'] = 'regie@example.test';
        unset($values['contact']['_token']);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(ConcertContact::class)->count([]));
    }

    public function testStaleContactFormDoesNotOverwriteAnotherMembersChanges(): void
    {
        $concert = $this->concert();
        $contact = new ConcertContact($concert);
        $contact->update('Camille', \App\Enum\ContactRole::Organizer, null, null, null);
        $this->entityManager()->persist($contact);
        $this->entityManager()->flush();
        $uri = '/contacts-concert/'.$contact->getId().'/modifier';
        $first = $this->client->request('GET', $uri)->filter('form[name="contact"]')->form();
        $second = $this->client->request('GET', $uri)->filter('form[name="contact"]')->form();
        $this->client->submit($first, ['contact' => ['notes' => 'Premier changement']]);
        self::assertResponseStatusCodeSame(303);
        $this->client->submit($second, ['contact' => ['notes' => 'Formulaire périmé']]);
        self::assertResponseStatusCodeSame(409);
        $this->entityManager()->clear();
        self::assertSame('Premier changement', $this->entityManager()->find(ConcertContact::class, $contact->getId())->getNotes());
    }

    #[DataProvider('collaboratingRoles')]
    public function testTeamMembersCanAddContacts(BandRole $role): void
    {
        $id = $this->concert()->getId();
        $this->loginRole($role);
        $form = $this->client->request('GET', '/concerts/'.$id.'/contacts/ajouter')->filter('form[name="contact"]')->form();
        $this->client->submit($form, ['contact' => ['name' => 'Contact partagé']]);
        self::assertResponseStatusCodeSame(303);
    }

    public static function collaboratingRoles(): iterable
    {
        yield [BandRole::Manager];
        yield [BandRole::Editor];
        yield [BandRole::Booker];
    }

    public function testViewersCannotModifyContacts(): void
    {
        $id = $this->concert()->getId();
        $this->loginRole(BandRole::Viewer);
        $this->client->request('GET', '/concerts/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/concerts/'.$id.'/contacts/ajouter"]');
        $this->client->request('POST', '/concerts/'.$id.'/contacts/ajouter');
        self::assertResponseStatusCodeSame(403);
    }
}
