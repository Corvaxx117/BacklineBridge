<?php

declare(strict_types=1);

namespace App\Tests\Functional\Technical;

use App\Dto\Technical\TechnicalIdentity;
use App\Entity\Band;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TechnicalInformationTest extends VenueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->band()->setTechnicalIdentity(TechnicalIdentity::fromArray([
            'style' => 'Indie / Garage',
            'description' => 'Live énergique',
            'contacts' => [['name' => 'Martin fictif', 'role' => 'Son / FOH', 'email' => 'sound@example.test', 'phone' => '01 00 00 00 01']],
        ]));
        $this->sheet()->setNotices(['Note de référence']);
        $this->entityManager()->flush();
    }

    public function testContactsHaveTheirOwnEditorAndPublishedCopiesRemainFrozen(): void
    {
        $publication = $this->issue()->invitation->getPublication();
        $beforeNotices = $this->sheet()->getNotices();
        $values = $this->values('contacts');
        $values['technical_contacts']['contacts'][0]['name'] = 'Contact actualisé';
        $this->client->request('POST', $this->url('contacts'), $values);
        self::assertResponseRedirects('/#sheet-notes', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.rider-contact', 'Contact actualisé');
        self::assertSelectorExists('a[aria-label="Modifier les contacts"]');
        self::assertSelectorExists('a[aria-label="Modifier les notes"]');
        self::assertSame($beforeNotices, $this->sheet()->getNotices());
        self::assertSame('Indie / Garage', $this->band()->getTechnicalIdentity()->style);
        self::assertSame('Martin fictif', $publication->getSnapshot()['technicalIdentity']['contacts'][0]['name']);
    }

    public function testConcertNotesAreLocalAndContactsArePreserved(): void
    {
        $concert = $this->concert();
        $id = $concert->getPatchSheet()->getId();
        $beforeContacts = $this->band()->getTechnicalIdentity()->contacts;
        $values = $this->values('notes', $id);
        $values['technical_notes']['notices'] = "Accueil technique à 16 h.\nPrévoir un câble.\n\nStationnement derrière la salle.";
        $values['technical_notes']['style'] = 'Psychedelic / Indie / Garage';
        $this->client->request('POST', $this->url('notes', $id), $values);
        self::assertResponseRedirects('/concerts/'.$concert->getId().'#technical-notes', 303);
        $this->entityManager()->clear();
        $sheet = $this->entityManager()->find(TechnicalSheet::class, $id);
        self::assertSame(["Accueil technique à 16 h.\nPrévoir un câble.", 'Stationnement derrière la salle.'], $sheet->getNotices());
        self::assertSame(['Note de référence'], $this->sheet()->getNotices());
        self::assertSame($beforeContacts, $this->band()->getTechnicalIdentity()->contacts);
        self::assertSame('Psychedelic / Indie / Garage', $this->band()->getTechnicalIdentity()->style);
    }

    public function testSeparateEditorsDoNotOverwriteEachOtherButRejectStaleVersionsOfTheirOwnSection(): void
    {
        $contacts = $this->values('contacts');
        $notes = $this->values('notes');
        $contacts['technical_contacts']['contacts'][0]['name'] = 'Coordonnées récentes';
        $this->client->request('POST', $this->url('contacts'), $contacts);
        self::assertResponseStatusCodeSame(303);
        $notes['technical_notes']['notices'] = 'Note récente';
        $this->client->request('POST', $this->url('notes'), $notes);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('Coordonnées récentes', $this->band()->getTechnicalIdentity()->contacts[0]['name']);
        $notes['technical_notes']['notices'] = 'Ancienne version';
        $this->client->request('POST', $this->url('notes'), $notes);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorExists('.form-error-summary');
        $contacts['technical_contacts']['contacts'][0]['name'] = 'Anciennes coordonnées';
        $this->client->request('POST', $this->url('contacts'), $contacts);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['Note récente'], $this->sheet()->getNotices());
        self::assertSame('Coordonnées récentes', $this->band()->getTechnicalIdentity()->contacts[0]['name']);
    }

    public function testInvalidCoordinatesExtraFieldsAndMissingCsrfDoNotChangeData(): void
    {
        $values = $this->values('contacts');
        $values['technical_contacts']['contacts'][0]['email'] = 'javascript:unsafe';
        $this->client->request('POST', $this->url('contacts'), $values);
        self::assertResponseStatusCodeSame(422);
        $values['technical_contacts']['contacts'][0]['email'] = 'valid@example.test';
        $values['technical_contacts']['contacts'][0]['socialSecurityNumber'] = 'should-not-be-accepted';
        $this->client->request('POST', $this->url('contacts'), $values);
        self::assertResponseStatusCodeSame(422);
        unset($values['technical_contacts']['contacts'][0]['socialSecurityNumber'], $values['technical_contacts']['_token']);
        $this->client->request('POST', $this->url('contacts'), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('sound@example.test', $this->band()->getTechnicalIdentity()->contacts[0]['email']);
        $notes = $this->values('notes');
        $notes['technical_notes']['notices'] = str_repeat('x', 12001);
        $this->client->request('POST', $this->url('notes'), $notes);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Note de référence'], $this->sheet()->getNotices());
    }

    public function testContactsCanBeRemovedAndEmptyNotesStillHaveAnEditAction(): void
    {
        $values = $this->values('contacts');
        $values['technical_contacts']['contacts'][0]['remove'] = '1';
        $this->client->request('POST', $this->url('contacts'), $values);
        self::assertResponseStatusCodeSame(303);
        self::assertSame([], $this->band()->getTechnicalIdentity()->contacts);
        $values = $this->values('notes');
        $values['technical_notes']['notices'] = '';
        $this->client->request('POST', $this->url('notes'), $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('#sheet-notes a[aria-label="Modifier les notes"]');
        self::assertSelectorExists('#sheet-notes a[aria-label="Modifier les contacts"]');
    }

    #[DataProvider('readOnlyRoles')]
    public function testReadersAndOtherGroupsCannotEdit(BandRole $role, bool $otherBand): void
    {
        $band = $this->band();
        if ($otherBand) {
            $band = new Band('Autre groupe', new TechnicalSheet());
            $this->entityManager()->persist($band);
            $this->entityManager()->flush();
        }
        $user = static::getContainer()->get(UserCreator::class)->create('reader@example.test', 'Lecteur', 'CompteTest2026!', $band, $role);
        $this->client->loginUser($user);
        foreach (['contacts', 'notes'] as $mode) {
            $this->client->request('GET', $this->url($mode));
            self::assertResponseStatusCodeSame(403);
            $this->client->request('POST', $this->url($mode), []);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public static function readOnlyRoles(): iterable
    {
        yield [BandRole::Viewer, false];
        yield [BandRole::Booker, false];
        yield [BandRole::Editor, false];
        yield [BandRole::Manager, true];
    }

    private function band(): Band
    {
        return $this->entityManager()->find(Band::class, $this->bandId);
    }

    private function url(string $mode, ?int $sheet = null): string
    {
        return '/fiches/'.($sheet ?? $this->sheetId).'/'.$mode;
    }

    private function values(string $mode, ?int $sheet = null): array
    {
        $crawler = $this->client->request('GET', $this->url($mode, $sheet));
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[name="technical_'.$mode.'"]')->form()->getPhpValues();
    }
}
