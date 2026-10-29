<?php

declare(strict_types=1);

namespace App\Tests\Functional\Venue;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\{Concert, VenueInvitation, VenueReply};
use App\Service\Concert\ConcertConditionsUpdater;
use App\Service\Venue\PublicationBuilder;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class GuestWorkspaceTest extends VenueTestCase
{
    public function testRepeatedCategoriesKeepEveryRequestInOneGroup(): void
    {
        $equipment = $this->sheet()->getEquipmentRequests();
        $equipment[array_key_last($equipment)]['category'] = 'Batterie';
        $equipment[9]['category'] = '0';
        $this->sheet()->setEquipmentRequests($equipment);
        $this->entityManager()->flush();
        $this->enter($this->issue()->token);
        $crawler = $this->client->getCrawler();
        self::assertCount(1, $crawler->filter('[data-reply-group][data-group-label="Batterie"]'));
        self::assertCount(9, $crawler->filter('[data-group-label="Batterie"] [data-answer-row]'));
        self::assertCount(1, $crawler->filter('[data-group-label="0"] [data-answer-row]'));
        self::assertCount(41, $crawler->filter('[data-answer-row]'));
        foreach ($equipment as $item) {
            self::assertCount(
                4,
                $crawler->filter(
                    'input[name="venue_reply[answers][gear_' . $item['id'] . '][status]"]',
                ),
            );
        }
    }

    public function testAnInvalidAlternativeIsVisibleInsideItsCategory(): void
    {
        $this->enter($this->issue()->token);
        $values = $this->replyValues();
        $values['venue_reply']['respondent'] = 'Régie';
        $values['venue_reply']['answers']['gear_bass_cab'] = [
            'status' => 'alternative',
            'details' => '',
        ];
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-group-label="Amplification"][open]');
        self::assertSelectorExists(
            '[data-group-label="Amplification"] [data-answer-state="alternative"] [data-answer-details][open]',
        );
        self::assertSelectorTextContains(
            '[data-group-label="Amplification"] .field-errors li',
            'Précisez le matériel proposé en remplacement.',
        );
        self::assertSame(0, $this->entityManager()->getRepository(VenueReply::class)->count([]));
    }

    public function testGuestSeesOnlyThePublishedTechnicalDossier(): void
    {
        $concert = $this->concert();
        $input = new ConcertConditionsInput();
        $input->feeAmount = '9876.54';
        $input->notes = 'SECRET FINANCIER';
        static::getContainer()->get(ConcertConditionsUpdater::class)->save($concert, $input);
        $issued = $this->issue($concert);
        self::assertSame(hash('sha256', $issued->token), $issued->invitation->getTokenHash());
        self::assertNotSame($issued->token, $issued->invitation->getTokenHash());
        self::assertStringNotContainsString(
            'SECRET FINANCIER',
            json_encode($issued->invitation->getPublication()->getSnapshot()),
        );
        $this->enter($issued->token);
        self::assertSelectorTextContains('h1', 'Salle de test');
        self::assertSelectorTextContains('main', 'Accueil à 16 h.');
        self::assertSelectorTextContains('#plan-de-scene', 'SYLOU');
        self::assertSelectorTextContains('#backline', 'Cab basse');
        self::assertSelectorTextNotContains('main', 'SECRET FINANCIER');
        self::assertSelectorTextNotContains('main', '9 876,54');
        self::assertSelectorNotExists('.site-nav');
        self::assertSelectorNotExists('a[href*="/plan-de-scene"]');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString(
            'no-store',
            $this->client->getResponse()->headers->get('Cache-Control'),
        );
        $this->client->request('GET', '/concerts/' . $concert->getId());
        self::assertResponseRedirects();
        self::assertSame(
            '/connexion',
            parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH),
        );
    }

    public function testPartialResponseIsPersistedEditableAndVisibleToTheGroup(): void
    {
        $issued = $this->issue();
        $concertId = $issued->invitation->getPublication()->getConcert()->getId();
        $this->enter($issued->token);
        $values = $this->replyValues();
        $values['venue_reply']['respondent'] = 'Alex, régie';
        $values['venue_reply']['notes'] = "Repas à 19 h.\n<script>alert('test')</script>";
        $values['venue_reply']['answers']['gear_kick'] = [
            'status' => 'available',
            'details' => 'Gretsch 22 pouces',
        ];
        $values['venue_reply']['answers']['gear_bass_cab'] = [
            'status' => 'alternative',
            'details' => 'Cab 2 × 12, 500 W / 4 Ω',
        ];
        $firstLineKey = array_key_first(
            array_filter(
                $values['venue_reply']['answers'],
                static fn($key): bool => str_starts_with($key, 'line_'),
                ARRAY_FILTER_USE_KEY,
            ),
        );
        $values['venue_reply']['answers'][$firstLineKey] = [
            'status' => 'unavailable',
            'details' => 'Proposition à convenir.',
        ];
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseRedirects($this->guestUrl(), 303);
        $em = $this->entityManager();
        $em->clear();
        $reply = $em->getRepository(VenueReply::class)->findOneBy([]);
        self::assertSame('Alex, régie', $reply->getRespondent());
        self::assertSame('available', $reply->getAnswers()['gear_kick']['status']);
        self::assertSame('unknown', $reply->getAnswers()['gear_floor_tom']['status']);
        $values = $this->replyValues();
        self::assertSame(
            'alternative',
            $values['venue_reply']['answers']['gear_bass_cab']['status'],
        );
        $values['venue_reply']['answers']['gear_kick']['status'] = 'unavailable';
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->entityManager()->getRepository(VenueReply::class)->count([]));
        $this->client->loginUser(
            $this->entityManager()->find(\App\Entity\User::class, $this->userId),
        );
        $this->client->request('GET', '/concerts/' . $concertId . '/reponses-salle');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Cab 2 × 12, 500 W / 4 Ω');
        self::assertSelectorNotExists('main script');
        self::assertSelectorTextContains('.response-author', "<script>alert('test')</script>");
    }

    #[DataProvider('invalidReplies')]
    public function testInvalidOrForgedResponsesDoNotPersist(string $case): void
    {
        $this->enter($this->issue()->token);
        $values = $this->replyValues();
        $values['venue_reply']['respondent'] = 'Régie';
        match ($case) {
            'csrf' => ($values['venue_reply']['_token'] = 'invalid'),
            'alternative without details' => ($values['venue_reply']['answers']['gear_kick'] = [
                'status' => 'alternative',
                'details' => '',
            ]),
            'unknown enum' => ($values['venue_reply']['answers']['gear_kick']['status'] = 'hacked'),
            'missing enum' => ($values['venue_reply']['answers']['gear_kick']['status'] = ''),
            'other request' => ($values['venue_reply']['answers']['gear_unauthorized'] = [
                'status' => 'available',
                'details' => '',
            ]),
            'too long' => ($values['venue_reply']['notes'] = str_repeat('é', 2001)),
        };
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(VenueReply::class)->count([]));
    }

    public static function invalidReplies(): iterable
    {
        foreach (
            [
                'csrf',
                'alternative without details',
                'unknown enum',
                'missing enum',
                'other request',
                'too long',
            ]
            as $case
        ) {
            yield [$case];
        }
    }

    public function testStaleReplyCannotOverwriteANewerReply(): void
    {
        $this->enter($this->issue()->token);
        $stale = $this->replyValues();
        $stale['venue_reply']['respondent'] = 'Régie initiale';
        $latest = $stale;
        $latest['venue_reply']['respondent'] = 'Nouvelle réponse';
        $this->client->request('POST', $this->guestUrl(), $latest);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('POST', $this->guestUrl(), $stale);
        self::assertResponseStatusCodeSame(409);
        $em = $this->entityManager();
        $em->clear();
        self::assertSame(
            'Nouvelle réponse',
            $em->getRepository(VenueReply::class)->findOneBy([])->getRespondent(),
        );
    }

    public function testRevokingALinkAlsoRevokesAnAlreadyOpenGuestSession(): void
    {
        $issued = $this->issue();
        $id = $issued->invitation->getId();
        $this->enter($issued->token);
        $values = $this->replyValues();
        $values['venue_reply']['respondent'] = 'Régie';
        $this->entityManager()->find(VenueInvitation::class, $id)->revoke();
        $this->entityManager()->flush();
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Ce lien est indisponible');
        self::assertSame(0, $this->entityManager()->getRepository(VenueReply::class)->count([]));
        $this->client->request('GET', '/invitation/' . $issued->token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownExpiredAndMissingLinksCannotOpenTheDossier(): void
    {
        $issued = $this->issue();
        $token = str_repeat('e', 64);
        $expired = new VenueInvitation(
            $issued->invitation->getPublication(),
            'Expiré',
            hash('sha256', $token),
            new \DateTimeImmutable('-1 minute'),
        );
        $this->entityManager()->persist($expired);
        $this->entityManager()->flush();
        $this->client->getCookieJar()->clear();
        foreach (
            ['/salle', '/invitation/' . $token, '/invitation/' . str_repeat('0', 64)]
            as $uri
        ) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(404);
            self::assertSelectorTextNotContains('main', 'Salle de test');
        }
    }

    public function testPublicationStaysStableAfterTechnicalAndConcertChanges(): void
    {
        $issued = $this->issue();
        $publication = $issued->invitation->getPublication();
        $concert = $publication->getConcert();
        $concert->updateDetails(
            $concert->getDate(),
            'Nouvelle salle privée',
            'Paris',
            $concert->getStatus(),
            'NOUVELLE NOTE',
        );
        $concert->getPatchSheet()->setStagePlan([])->setEquipmentRequests([]);
        $concert->getPatchSheet()->getLines()->first()->setSourceName('NOUVELLE SOURCE');
        $this->entityManager()->flush();
        self::assertTrue(
            static::getContainer()->get(PublicationBuilder::class)->isOutdated($publication),
        );
        $this->enter($issued->token);
        self::assertSelectorTextContains('h1', 'Salle de test');
        self::assertSelectorTextContains('#backline', 'Cab basse');
        self::assertSelectorTextNotContains('main', 'NOUVELLE NOTE');
        self::assertSelectorTextNotContains('main', 'NOUVELLE SOURCE');
    }
}
