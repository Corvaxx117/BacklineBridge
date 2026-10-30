<?php

declare(strict_types=1);

namespace App\Tests;

use App\Dto\Venue\InvitationInput;
use App\Dto\Venue\IssuedInvitation;
use App\Entity\Concert;
use App\Service\Concert\ConcertCreator;
use App\Service\Venue\InvitationIssuer;

abstract class VenueTestCase extends DatabaseTestCase
{
    protected string $guestAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
        $data = json_decode(
            file_get_contents(dirname(__DIR__).'/data/avee-mana-stage.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->sheet()->setStagePlan($data['stagePlan'])->setEquipmentRequests($data['equipment']);
        $this->entityManager()->flush();
    }

    protected function concert(): Concert
    {
        return static::getContainer()
            ->get(ConcertCreator::class)
            ->create(
                $this->sheet(),
                new \DateTimeImmutable('2026-12-12'),
                'Salle de test',
                'Lyon',
                notes: 'Accueil à 16 h.',
            );
    }

    protected function issue(?Concert $concert = null): IssuedInvitation
    {
        $input = new InvitationInput();
        $input->label = 'Régie de test';

        return static::getContainer()
            ->get(InvitationIssuer::class)
            ->issue($concert ?? $this->concert(), $input);
    }

    protected function enter(string $token): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/invitation/'.$token);
        self::assertResponseStatusCodeSame(303);
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertSame('/salle', parse_url($location, PHP_URL_PATH));
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $query['access']);
        $this->guestAccess = $query['access'];
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    protected function guestUrl(string $path = '/salle'): string
    {
        return $path.'?access='.$this->guestAccess;
    }

    protected function replyValues(): array
    {
        $crawler = $this->client->request('GET', $this->guestUrl());
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[name="venue_reply"]')->form()->getPhpValues();
    }
}
