<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\Concert;
use App\Entity\TechnicalSheet;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateActionTest extends DatabaseTestCase
{
    public function testCreatingAConcertPersistsItsDetailsAndOrderedPatch(): void
    {
        $this->submitConcert();

        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        $concert = $em->getRepository(Concert::class)->findOneBy([]);
        self::assertInstanceOf(Concert::class, $concert);
        self::assertResponseRedirects('/concerts/' . $concert->getId(), 303);
        self::assertSame('2026-11-14', $concert->getDate()->format('Y-m-d'));
        self::assertSame('Salle de test', $concert->getVenue());
        self::assertSame('Marseille', $concert->getCity());
        self::assertNull($concert->getNotes());
        self::assertNotSame($this->sheetId, $concert->getPatchSheet()->getId());

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Salle de test');
        self::assertSelectorTextContains('.hero-description', '14/11/2026');
        self::assertSelectorNotExists('.concert-notes');
        self::assertCount(22, $crawler->filter('table tbody tr'));
        self::assertSame(
            range(1, 22),
            $crawler->filter('.channel-number')->each(static fn($node): int => (int) $node->text()),
        );
    }

    public function testNotesArePersistedAndDisplayedAsEscapedMultilineText(): void
    {
        $notes = "Repas sur place à 19 h.\n<script>alert(\"notes\")</script>";
        $this->submitConcert(['notes' => $notes]);

        self::assertResponseStatusCodeSame(303);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame($notes, $em->getRepository(Concert::class)->findOneBy([])->getNotes());

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.concert-notes', 'Repas sur place à 19 h.');
        self::assertSelectorTextContains('.concert-notes', '<script>alert("notes")</script>');
        self::assertSelectorNotExists('.concert-notes script');
        self::assertCount(1, $crawler->filter('.concert-notes-content br'));
    }

    #[DataProvider('invalidDetails')]
    public function testInvalidDetailsNeverCreateAPartialDossier(
        array $changes,
        string $message,
    ): void {
        $this->submitConcert($changes);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="concert"]', $message);
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
        self::assertSame(
            1,
            $this->entityManager()->getRepository(TechnicalSheet::class)->count([]),
        );
        $this->assertLineCount(22);
    }

    public static function invalidDetails(): iterable
    {
        yield 'missing date' => [['date' => ''], 'Indique la date'];
        yield 'impossible date' => [['date' => '2026-02-30'], 'date valide'];
        yield 'invalid date' => [['date' => 'demain'], 'date valide'];
        yield 'blank venue' => [['venue' => '   '], 'nom de la salle'];
        yield 'blank city' => [['city' => ''], 'ville du concert'];
        yield 'long venue' => [['venue' => str_repeat('a', 151)], '150 caractères'];
        yield 'long city' => [['city' => str_repeat('a', 121)], '120 caractères'];
        yield 'long notes' => [['notes' => str_repeat('é', 2001)], '2000 caractères'];
    }

    public function testMissingCsrfTokenCannotCreateAConcert(): void
    {
        $this->submitConcert(withCsrf: false);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="concert"]', 'Le formulaire a expiré');
        self::assertSame(0, $this->entityManager()->getRepository(Concert::class)->count([]));
        self::assertSame(
            1,
            $this->entityManager()->getRepository(TechnicalSheet::class)->count([]),
        );
    }

    private function submitConcert(array $changes = [], bool $withCsrf = true): void
    {
        $crawler = $this->client->request('GET', '/concerts/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="concert"]')->form();
        $values = $form->getPhpValues();
        $values['concert'] = array_replace(
            $values['concert'],
            [
                'date' => '2026-11-14',
                'venue' => 'Salle de test',
                'city' => 'Marseille',
            ],
            $changes,
        );

        if (!$withCsrf) {
            unset($values['concert']['_token']);
        }

        $this->client->request('POST', '/concerts/new', $values);
    }
}
