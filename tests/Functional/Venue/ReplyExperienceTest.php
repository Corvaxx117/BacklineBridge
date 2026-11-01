<?php

declare(strict_types=1);

namespace App\Tests\Functional\Venue;

use App\Entity\VenueReply;
use App\Tests\VenueTestCase;

final class ReplyExperienceTest extends VenueTestCase
{
    public function testTheReplyComesBeforeThePlanAndKeepsEveryFieldWithoutJavascript(): void
    {
        $this->enter($this->issue()->token);
        $crawler = $this->client->getCrawler();
        self::assertSelectorExists('.guest-entry-actions a[href="#confirmation-materiel"]');
        self::assertCount(1, $crawler->filter('input[name="venue_reply[respondent]"]'));
        self::assertCount(41, $crawler->filter('[data-answer-row]'));
        self::assertCount(164, $crawler->filter('[data-answer-choices] input:not([disabled])'));
        self::assertSelectorExists('form[data-reply-saved="false"]');
        self::assertSelectorExists('[data-reply-save-bar][hidden]');
        self::assertSelectorExists('[data-reply-filter="pending"]');
        self::assertSelectorExists('.reply-contact button[type="submit"]:not([disabled])');
        self::assertCount(1, $crawler->filterXPath('//*[@id="plan-de-scene"]/preceding::form[@data-venue-reply]'));
        self::assertCount(1, $crawler->filterXPath(
            '//*[@data-reply-group][1]/preceding::input[@name="venue_reply[respondent]"]',
        ));
    }

    public function testSavingAgainPreservesEarlierAnswersAndTheirDetails(): void
    {
        $this->enter($this->issue()->token);
        $values = $this->replyValues();
        $values['venue_reply']['respondent'] = 'Régie de test';
        $values['venue_reply']['answers']['gear_bass_cab'] = [
            'status' => 'alternative',
            'details' => 'Cab 2 × 12 disponible.',
        ];
        $values['venue_reply']['answers']['gear_kick'] = [
            'status' => 'available',
            'details' => 'Grosse caisse 22 pouces.',
        ];
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('form[data-reply-saved="true"]');
        self::assertInputValueSame('venue_reply[respondent]', 'Régie de test');
        $values = $this->replyValues();
        self::assertSame('alternative', $values['venue_reply']['answers']['gear_bass_cab']['status']);
        self::assertSame('Cab 2 × 12 disponible.', $values['venue_reply']['answers']['gear_bass_cab']['details']);
        $lineKey = array_key_first(array_filter(
            $values['venue_reply']['answers'],
            static fn (string $key): bool => str_starts_with($key, 'line_'),
            ARRAY_FILTER_USE_KEY,
        ));
        self::assertNotNull($lineKey);
        $values['venue_reply']['answers'][$lineKey]['status'] = 'unavailable';
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(303);
        $this->entityManager()->clear();
        $reply = $this->entityManager()->getRepository(VenueReply::class)->findOneBy([]);
        self::assertCount(41, $reply->getAnswers());
        self::assertSame('Cab 2 × 12 disponible.', $reply->getAnswers()['gear_bass_cab']['details']);
        self::assertSame('available', $reply->getAnswers()['gear_kick']['status']);
        self::assertSame('Grosse caisse 22 pouces.', $reply->getAnswers()['gear_kick']['details']);
        self::assertSame('unavailable', $reply->getAnswers()[$lineKey]['status']);
    }

    public function testAnInvalidResponseKeepsItsValuesAndCanBeSubmittedAgain(): void
    {
        $this->enter($this->issue()->token);
        $values = $this->replyValues();
        $values['venue_reply']['respondent'] = 'Régie de test';
        $values['venue_reply']['answers']['gear_bass_cab'] = ['status' => 'alternative', 'details' => ''];
        $this->client->request('POST', $this->guestUrl(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('venue_reply[respondent]', 'Régie de test');
        self::assertSelectorExists('[data-group-label="Amplification"][open]');
        self::assertSelectorExists('[data-reply-save-button]:not([disabled])');
        self::assertSelectorExists('[data-answer-state="alternative"] [data-answer-details][open]');
        self::assertSame(0, $this->entityManager()->getRepository(VenueReply::class)->count([]));
    }
}
