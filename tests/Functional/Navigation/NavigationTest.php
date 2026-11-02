<?php

declare(strict_types=1);

namespace App\Tests\Functional\Navigation;

use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NavigationTest extends DatabaseTestCase
{
    public function testGroupPagesAreGroupedAndTheCurrentPageRemainsIdentifiable(): void
    {
        $em = $this->entityManager();
        $em->persist(new MemberProfile(
            $em->find(Band::class, $this->bandId),
            $em->find(User::class, $this->userId),
            'Musicien test', 'Guitare', MemberKind::Musician,
        ));
        $em->flush();
        $crawler = $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSame(['Concerts', 'Agenda', 'Fiche technique'], $crawler->filter('#primary-navigation > .nav-primary-link')->each(static fn ($node): string => $node->text()));
        self::assertSelectorExists('[data-nav-menu]:not([open])');
        self::assertSelectorCount(4, '.nav-group-links a');
        self::assertSelectorExists('.nav-group-links a[href="/groupes/'.$this->bandId.'/tresorerie"]');
        $this->client->request('GET', '/groupes/'.$this->bandId.'/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-nav-menu][data-current="true"]');
        self::assertSelectorTextContains('.nav-group-links a[aria-current="page"]', 'Documents');
    }

    #[DataProvider('nonMusicians')]
    public function testGroupingPreservesTheRestrictedTreasuryLink(MemberKind $kind, BandRole $role): void
    {
        $em = $this->entityManager();
        $band = $em->find(Band::class, $this->bandId);
        $user = static::getContainer()->get(UserCreator::class)->create('member@example.test', 'Membre test', 'MotDePasse2026!', $band, $role);
        $em->persist(new MemberProfile($band, $user, 'Membre test', $kind->label(), $kind));
        $em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#primary-navigation a[href="/groupes/'.$this->bandId.'/agenda"]');
        self::assertSelectorCount(3, '.nav-group-links a');
        self::assertSelectorNotExists('.nav-group-links a[href="/groupes/'.$this->bandId.'/tresorerie"]');
        self::assertSelectorTextContains('[data-nav-menu] > summary', 'Le groupe');
    }

    public static function nonMusicians(): iterable
    {
        yield 'Ingénieur son' => [MemberKind::Engineer, BandRole::Manager];
        yield 'Booker' => [MemberKind::Booker, BandRole::Booker];
    }
}
