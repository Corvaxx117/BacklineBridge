<?php

declare(strict_types=1);

namespace App\Tests\Functional\Patch;

use App\Entity\Band;
use App\Entity\PatchLine;
use App\Entity\User;
use App\Enum\BandRole;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ArchiveActionTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
    }

    public function testAManagerCanRemoveAndUndoWithoutChangingTheSourceIdentity(): void
    {
        $lineId = $this->lineForChannel(17)->getId();
        $this->removeFromPage('/', $lineId);
        self::assertResponseRedirects('/', 303);
        $crawler = $this->client->followRedirect();
        self::assertCount(21, $crawler->filter('table tbody tr'));
        self::assertSelectorTextNotContains('table', 'JU VOICE');
        $this->entityManager()->clear();
        self::assertFalse($this->entityManager()->find(PatchLine::class, $lineId)->isActive());
        $this->client->submit(
            $crawler->filter('form[action="/sources/' . $lineId . '/restore"]')->form(),
        );
        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'JU VOICE');
        $this->entityManager()->clear();
        self::assertSame($lineId, $this->lineForChannel(17)->getId());
        self::assertTrue($this->lineForChannel(17)->isActive());
    }

    public function testRemovingAConcertSourceLeavesTheReferenceUntouched(): void
    {
        $concert = static::getContainer()
            ->get(ConcertCreator::class)
            ->create($this->sheet(), new \DateTimeImmutable('2026-12-12'), 'Salle', 'Lyon');
        $id = $concert->getId();
        $lineId = $concert->getPatchSheet()->getLines()->first()->getId();
        $this->removeFromPage('/concerts/' . $id, $lineId);
        self::assertResponseRedirects('/concerts/' . $id, 303);
        $crawler = $this->client->followRedirect();
        self::assertCount(21, $crawler->filter('table tbody tr'));
        $this->entityManager()->clear();
        self::assertSame(
            22,
            $this->entityManager()
                ->getRepository(PatchLine::class)
                ->count(['sheet' => $this->sheetId, 'active' => true]),
        );
    }

    public function testAReusedChannelBlocksRestorationWithoutOverwritingEitherSource(): void
    {
        $oldId = $this->lineForChannel(17)->getId();
        $this->removeFromPage('/', $oldId);
        $this->submitLineForm(
            '/sources/new',
            array_replace($this->newLineValues(), ['channelNumber' => '17']),
        );
        self::assertResponseRedirects('/', 303);
        $crawler = $this->client->request('GET', '/patchs/' . $this->sheetId . '/corbeille');
        $this->client->submit(
            $crawler->filter('form[action="/sources/' . $oldId . '/restore"]')->form(),
        );
        self::assertResponseRedirects('/patchs/' . $this->sheetId . '/corbeille', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'canal est déjà utilisé');
        $em = $this->entityManager();
        $em->clear();
        self::assertFalse($em->find(PatchLine::class, $oldId)->isActive());
        self::assertSame('JU VOICE', $em->find(PatchLine::class, $oldId)->getSourceName());
        $replacement = $em
            ->getRepository(PatchLine::class)
            ->findOneBy(['sheet' => $this->sheetId, 'channelNumber' => 17, 'active' => true]);
        self::assertSame('VOIX INVITÉE', $replacement->getSourceName());
        $replacementId = $replacement->getId();
        $this->removeFromPage('/', $replacementId);
        self::assertResponseRedirects('/', 303);
        $crawler = $this->client->request('GET', '/patchs/' . $this->sheetId . '/corbeille');
        self::assertCount(2, $crawler->filter('.trash-list li'));
        $this->client->submit(
            $crawler->filter('form[action="/sources/' . $oldId . '/restore"]')->form(),
        );
        self::assertResponseRedirects('/', 303);
        $this->entityManager()->clear();
        self::assertTrue($this->entityManager()->find(PatchLine::class, $oldId)->isActive());
        self::assertFalse(
            $this->entityManager()->find(PatchLine::class, $replacementId)->isActive(),
        );
    }

    public function testNewConcertsCopyOnlyActiveSourcesAndArchivedSourcesCannotBeEdited(): void
    {
        $id = $this->lineForChannel(17)->getId();
        $this->removeFromPage('/', $id);
        $this->client->request('GET', '/sources/' . $id . '/edit');
        self::assertResponseStatusCodeSame(404);
        $em = $this->entityManager();
        $em->clear();
        $concert = static::getContainer()
            ->get(ConcertCreator::class)
            ->create($this->sheet(), new \DateTimeImmutable('2026-12-12'), 'Salle', 'Lyon');
        self::assertCount(21, $concert->getPatchSheet()->getLines());
        self::assertSame(21, $concert->getPatchSheet()->getActiveLineCount());
    }

    public function testDeleteAndRestoreRequirePostAndValidCsrf(): void
    {
        $id = $this->lineForChannel(17)->getId();
        $this->client->request('GET', '/sources/' . $id . '/delete');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/sources/' . $id . '/delete');
        self::assertResponseStatusCodeSame(403);
        $this->entityManager()->clear();
        self::assertTrue($this->lineForChannel(17)->isActive());
        $this->removeFromPage('/', $id);
        $this->client->request('POST', '/sources/' . $id . '/restore');
        self::assertResponseStatusCodeSame(403);
        $this->entityManager()->clear();
        self::assertFalse($this->entityManager()->find(PatchLine::class, $id)->isActive());
    }

    #[DataProvider('restrictedRoles')]
    public function testOnlyManagersCanAccessRemovalAndTrash(BandRole $role): void
    {
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'restricted@example.test',
                'Accès restreint',
                'Restricted test password 2026!',
                $this->entityManager()->find(Band::class, $this->bandId),
                $role,
            );
        $id = $this->lineForChannel(17)->getId();
        $this->client->loginUser($user);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.delete-source');
        self::assertSelectorNotExists('.trash-link');
        foreach (['/sources/' . $id . '/delete', '/sources/' . $id . '/restore'] as $path) {
            $this->client->request('POST', $path);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET', '/patchs/' . $this->sheetId . '/corbeille');
        self::assertResponseStatusCodeSame(403);
        $this->entityManager()->clear();
        self::assertTrue($this->lineForChannel(17)->isActive());
    }

    public static function restrictedRoles(): iterable
    {
        yield [BandRole::Editor];
        yield [BandRole::Viewer];
        yield [BandRole::Booker];
    }

    public function testFivePeopleCanShareFullManagerRights(): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $managers = [$this->entityManager()->find(User::class, $this->userId)];
        foreach (['Membre 2', 'Membre 3', 'Membre 4', 'Marty'] as $index => $name) {
            $managers[] = static::getContainer()
                ->get(UserCreator::class)
                ->create(
                    'manager' . $index . '@example.test',
                    $name,
                    'Manager test password 2026!',
                    $band,
                    BandRole::Manager,
                );
        }
        foreach ($managers as $manager) {
            $this->client->loginUser($manager);
            $this->client->request('GET', '/');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.delete-source');
            self::assertSelectorTextContains('.role-badge', 'Responsable');
            $this->client->request('GET', '/concerts/new');
            self::assertResponseIsSuccessful();
        }
    }

    private function removeFromPage(string $uri, int $id): void
    {
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();
        $this->client->submit(
            $crawler->filter('form[action="/sources/' . $id . '/delete"]')->form(),
        );
    }
}
