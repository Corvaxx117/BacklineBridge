<?php

declare(strict_types=1);

namespace App\Tests\Functional\Venue;

use App\Entity\{VenueInvitation, VenueReply};
use App\Tests\VenueTestCase;

final class GuestIsolationTest extends VenueTestCase
{
    public function testTwoInvitationsInOneBrowserKeepTheirOwnReplies(): void
    {
        $concert = $this->concert();
        $first = $this->issue($concert);
        $second = $this->issue($concert);
        $firstId = $first->invitation->getId();
        $secondId = $second->invitation->getId();
        $this->enter($first->token);
        $firstUri = $this->guestUrl();
        $firstValues = $this->replyValues();
        $firstValues['venue_reply']['respondent'] = 'Première salle';

        // Même navigateur : conserver ses cookies et ouvrir le deuxième lien dans un autre onglet.
        $this->client->request('GET', '/invitation/' . $second->token);
        $secondUri = $this->client->getResponse()->headers->get('Location');
        self::assertNotSame($firstUri, $secondUri);
        $secondForm = $this->client->followRedirect()->filter('form[name="venue_reply"]')->form();

        $this->client->request('POST', $firstUri, $firstValues);
        self::assertResponseRedirects($firstUri, 303);
        $this->client->submit($secondForm, ['venue_reply' => ['respondent' => 'Deuxième salle']]);
        self::assertResponseRedirects($secondUri, 303);
        $this->entityManager()->clear();
        $replies = $this->entityManager()->getRepository(VenueReply::class);
        self::assertSame(
            'Première salle',
            $replies->findOneBy(['invitation' => $firstId])->getRespondent(),
        );
        self::assertSame(
            'Deuxième salle',
            $replies->findOneBy(['invitation' => $secondId])->getRespondent(),
        );

        // Même un envoi forcé du premier formulaire vers la seconde URL est refusé.
        $this->client->request('POST', $secondUri, $firstValues);
        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        self::assertSame(
            'Deuxième salle',
            $this->entityManager()
                ->getRepository(VenueReply::class)
                ->findOneBy(['invitation' => $secondId])
                ->getRespondent(),
        );
    }

    public function testThePdfLinkKeepsThePublicationOfItsOwnTab(): void
    {
        $first = $this->issue();
        $other = $this->concert();
        $other->updateDetails($other->getDate(), 'Seconde salle', 'Paris', $other->getStatus());
        $this->entityManager()->flush();
        $second = $this->issue($other);
        $this->enter($first->token);
        $firstPdf = $this->client
            ->getCrawler()
            ->selectLink('Télécharger la fiche PDF ↓')
            ->link()
            ->getUri();
        $this->client->request('GET', '/invitation/' . $second->token);
        $secondPdf = $this->client
            ->followRedirect()
            ->selectLink('Télécharger la fiche PDF ↓')
            ->link()
            ->getUri();

        $this->client->request('GET', $firstPdf);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame(
            'Content-Disposition',
            'attachment; filename=fiche-technique-avee-mana-2026-12-12-salle-de-test.pdf',
        );
        $this->client->request('GET', $secondPdf);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame(
            'Content-Disposition',
            'attachment; filename=fiche-technique-avee-mana-2026-12-12-seconde-salle.pdf',
        );
    }

    public function testLeavingOneAccessDoesNotCloseTheOtherAndCannotTargetItWithAnotherCsrf(): void
    {
        $first = $this->issue();
        $second = $this->issue();
        $this->enter($first->token);
        $firstUri = $this->guestUrl();
        $exit = $this->client->getCrawler()->selectButton('Quitter cet accès')->form();
        $this->client->request('GET', '/invitation/' . $second->token);
        $secondUri = $this->client->getResponse()->headers->get('Location');
        $secondExitUri = $this->client
            ->followRedirect()
            ->selectButton('Quitter cet accès')
            ->form()
            ->getUri();

        $this->client->request('POST', $secondExitUri, $exit->getPhpValues());
        self::assertResponseStatusCodeSame(403);
        $this->client->submit($exit);
        self::assertResponseRedirects('/connexion', 303);
        $this->client->request('GET', $firstUri);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $secondUri);
        self::assertResponseIsSuccessful();
    }

    public function testContextsRequireTheirSessionAndUnscopedRequestsCannotSelectALastInvitation(): void
    {
        $this->enter($this->issue()->token);
        $uri = $this->guestUrl();
        $values = $this->replyValues();
        foreach (['/salle', '/salle/fiche-technique.pdf'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', '/salle', $values);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/salle?access=' . str_repeat('0', 32));
        self::assertResponseStatusCodeSame(404);
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->entityManager()->getRepository(VenueReply::class)->count([]));
    }

    public function testRevocationIsCheckedForEachContextWithoutClosingOtherInvitations(): void
    {
        $first = $this->issue();
        $second = $this->issue();
        $firstId = $first->invitation->getId();
        $this->enter($first->token);
        $firstUri = $this->guestUrl();
        $values = $this->replyValues();
        $this->client->request('GET', '/invitation/' . $second->token);
        $secondUri = $this->client->getResponse()->headers->get('Location');
        $this->client->followRedirect();
        $this->entityManager()->find(VenueInvitation::class, $firstId)->revoke();
        $this->entityManager()->flush();
        $this->client->request('POST', $firstUri, $values);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $secondUri);
        self::assertResponseIsSuccessful();
    }
}
