<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Security\Voter\BandVoter;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ManagerAccessTest extends VenueTestCase
{
    private function loginManager(Band $band): User
    {
        $user = static::getContainer()->get(UserCreator::class)->create(
            'manager@example.test', 'Manager fictif', 'CompteManager2026!', $band, BandRole::TeamManager,
        );
        $em = $this->entityManager();
        $em->persist(new MemberProfile($band, $user, 'Manager fictif', 'Manager', MemberKind::Manager));
        $em->flush();
        $this->client->loginUser($user);

        return $user;
    }

    public function testManagerCanOpenEveryNonFinancialWorkspaceAndTechnicalEditor(): void
    {
        $concert = $this->concert();
        $this->loginManager($concert->getBand());
        $router = static::getContainer()->get(UrlGeneratorInterface::class);
        $routes = [
            'app_concert_new' => [],
            'app_concert_edit' => ['id' => $concert->getId()],
            'app_stage_edit' => ['id' => $this->sheetId],
            'app_equipment_edit' => ['id' => $this->sheetId],
            'app_patch_trash' => ['id' => $this->sheetId],
            'app_task_index' => ['id' => $this->bandId],
            'app_document_index' => ['id' => $this->bandId],
            'app_profile_team' => ['id' => $this->bandId],
            'app_calendar_index' => ['id' => $this->bandId],
        ];
        foreach ($routes as $route => $parameters) {
            $this->client->request('GET', $router->generate($route, $parameters));
            self::assertResponseIsSuccessful($route);
        }
        $this->client->request('GET', '/concerts/'.$concert->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.role-badge', 'Manager');
        self::assertSelectorNotExists('form[name="concert_availability"]');
        $permissions = static::getContainer()->get(AuthorizationCheckerInterface::class);
        foreach ([BandVoter::VIEW, BandVoter::EDIT_PATCH, BandVoter::DELETE_PATCH,
            BandVoter::MANAGE_TECHNICAL, BandVoter::PLAN_CONCERT, BandVoter::CONDITIONS,
            BandVoter::COLLABORATE, BandVoter::CALENDAR, BandVoter::CALENDAR_PUBLISH,
            BandVoter::SETLIST] as $attribute) {
            self::assertTrue($permissions->isGranted($attribute, $concert->getBand()), $attribute);
        }
        foreach ([ConcertVoter::SHARE, ConcertVoter::DELETE, ConcertVoter::IMPORT_REFERENCE] as $attribute) {
            self::assertTrue($permissions->isGranted($attribute, $concert), $attribute);
        }
        self::assertFalse($permissions->isGranted(BandVoter::TREASURY, $concert->getBand()));
    }

    public function testManagerCanCreateAConcertWithoutBecomingAPerformer(): void
    {
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $user = $this->loginManager($band);
        $concert = static::getContainer()->get(ConcertCreator::class)->create(
            $this->sheet(), new \DateTimeImmutable('2026-12-20'), 'Salle fictive', 'Lyon', createdBy: $user,
        );
        self::assertSame('Manager fictif', $concert->getCreatedByName());
    }

    public function testManagerCannotAccessAnotherBandsWorkspace(): void
    {
        $concert = $this->concert();
        $other = new Band('Autre groupe fictif', new TechnicalSheet());
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $this->loginManager($other);
        foreach (['/concerts/'.$concert->getId(), '/groupes/'.$this->bandId.'/equipe',
            '/groupes/'.$this->bandId.'/documents', '/groupes/'.$this->bandId.'/tresorerie'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
        }
    }
}
