<?php

declare(strict_types=1);
namespace App\Tests\Functional\Member;
use App\Entity\{Band, User, MemberProfile};
use App\Enum\{BandRole, MemberKind};
use App\Service\Member\{ProfileCipher, AccountActivator};
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
final class MemberWorkspaceTest extends DatabaseTestCase
{
    private function profile(User $user, MemberKind $kind): MemberProfile
    {
        $em = $this->entityManager();
        $profile = new MemberProfile(
            $em->find(Band::class, $this->bandId),
            $user,
            $user->getDisplayName(),
            'Rôle test',
            $kind,
        );
        $profile->setAdministrativeCiphertext(
            static::getContainer()
                ->get(ProfileCipher::class)
                ->encrypt($profile, [
                    'Numéro administratif' => 'SECRET-ADMIN-TEST',
                    'Naissance' => 'DATE-PRIVEE',
                ]),
        );
        $em->persist($profile);
        $em->flush();
        return $profile;
    }
    private function pending(): array
    {
        $em = $this->entityManager();
        $user = new User('pending@example.test', 'Membre à activer');
        $em->persist($user);
        $em->persist(
            new \App\Entity\BandMembership(
                $em->find(Band::class, $this->bandId),
                $user,
                BandRole::Manager,
            ),
        );
        $profile = $this->profile($user, MemberKind::Musician);
        $code = static::getContainer()->get(AccountActivator::class)->issue($profile);
        $em->flush();
        $this->client->restart();
        $this->client->setServerParameter('REMOTE_ADDR', '198.51.100.' . random_int(1, 254));
        return [$profile, $code];
    }
    public function testEncryptedProfilesDoNotLeakInTeamPageOrDatabase(): void
    {
        $profile = $this->profile(
            $this->entityManager()->find(User::class, $this->userId),
            MemberKind::Musician,
        );
        $raw = $this->entityManager()
            ->getConnection()
            ->fetchOne('SELECT administrative_ciphertext FROM member_profile WHERE id = ?', [
                $profile->getId(),
            ]);
        self::assertStringNotContainsString('SECRET-ADMIN-TEST', $raw);
        $this->client->request('GET', '/groupes/' . $this->bandId . '/equipe');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'SECRET-ADMIN-TEST',
            $this->client->getResponse()->getContent(),
        );
        $this->client->request('GET', '/equipe/' . $profile->getPublicId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profile-fields', 'SECRET-ADMIN-TEST');
        self::assertStringContainsString(
            'no-store',
            $this->client->getResponse()->headers->get('Cache-Control'),
        );
    }
    #[DataProvider('visibility')]
    public function testAdministrativeVisibility(
        MemberKind $kind,
        BandRole $role,
        int $expected,
    ): void {
        $profile = $this->profile(
            $this->entityManager()->find(User::class, $this->userId),
            MemberKind::Musician,
        );
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'viewer@example.test',
                'Autre membre',
                'Different strong password 2026!',
                $band,
                $role,
            );
        $own = $this->profile($user, $kind);
        $this->client->loginUser($user);
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/equipe/' . $profile->getPublicId());
        self::assertResponseStatusCodeSame($expected);
        $this->client->request('GET', '/equipe/' . $own->getPublicId());
        self::assertResponseIsSuccessful();
    }
    public static function visibility(): iterable
    {
        yield 'Marty own only' => [MemberKind::Engineer, BandRole::Manager, 403];
        yield 'Romain can read' => [MemberKind::Booker, BandRole::Booker, 200];
        yield 'Musician can read' => [MemberKind::Musician, BandRole::Manager, 200];
    }
    public function testActivationConsumesCodeAndLogsInWithMatchingSecurePassword(): void
    {
        [$profile, $code] = $this->pending();
        $uri = '/premiere-connexion/' . $profile->getPublicId();
        $this->client->request('GET', '/premiere-connexion');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('select', 'Membre à activer');
        self::assertStringNotContainsString(
            'pending@example.test',
            $this->client->getResponse()->getContent(),
        );
        self::assertStringNotContainsString(
            'SECRET-ADMIN-TEST',
            $this->client->getResponse()->getContent(),
        );
        $form = $this->client->request('GET', $uri)->filter('form[name="activation"]')->form();
        $this->client->submit($form, [
            'activation' => [
                'code' => $code,
                'password' => [
                    'first' => 'Une longue phrase Corail!94 lune?',
                    'second' => 'Une longue phrase Corail!94 lune?',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertStringStartsWith(
            '/equipe/',
            $this->client->getResponse()->headers->get('Location'),
        );
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $em = $this->entityManager();
        $em->clear();
        $saved = $em->find(MemberProfile::class, $profile->getId());
        self::assertNull($saved->getActivationHash());
        self::assertTrue($saved->getUser()->isActivated());
        self::assertNotSame('Une longue phrase Corail!94 lune?', $saved->getUser()->getPassword());
        $this->client->restart();
        $this->client->catchExceptions(true);
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
    }
    #[DataProvider('badActivation')]
    public function testInvalidActivationDoesNotSetPassword(string $mode): void
    {
        [$profile, $code] = $this->pending();
        $uri = '/premiere-connexion/' . $profile->getPublicId();
        $form = $this->client->request('GET', $uri)->filter('form[name="activation"]')->form();
        $values = $form->getPhpValues();
        $password = 'Une longue phrase Corail!94 lune?';
        $values['activation']['code'] = $mode === 'code' ? 'INVALID' : $code;
        $values['activation']['password'] = [
            'first' => $mode === 'weak' ? 'password' : $password,
            'second' =>
                $mode === 'mismatch'
                    ? 'Different Password 2026!!!'
                    : ($mode === 'weak'
                        ? 'password'
                        : $password),
        ];
        if ($mode === 'csrf') {
            unset($values['activation']['_token']);
        }
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            '',
            $this->entityManager()
                ->getConnection()
                ->fetchOne('SELECT password FROM app_user WHERE id = ?', [
                    $profile->getUser()->getId(),
                ]),
        );
    }
    public static function badActivation(): iterable
    {
        yield ['code'];
        yield ['weak'];
        yield ['mismatch'];
        yield ['csrf'];
    }
    public function testExpiredCodeAndRateLimitAreEnforced(): void
    {
        [$profile, $code] = $this->pending();
        $uri = '/premiere-connexion/' . $profile->getPublicId();
        $form = $this->client->request('GET', $uri)->filter('form[name="activation"]')->form();
        $values = $form->getPhpValues();
        $values['activation']['code'] = 'INVALID';
        $values['activation']['password'] = [
            'first' => 'Longue phrase tilleul 2026 !!!',
            'second' => 'Longue phrase tilleul 2026 !!!',
        ];
        for ($i = 0; $i < 6; ++$i) {
            $this->client->request('POST', $uri, $values);
        }
        self::assertResponseStatusCodeSame(429);
        $this->entityManager()
            ->getConnection()
            ->executeStatement(
                "UPDATE member_profile SET activation_expires_at = '2020-01-01 00:00:00' WHERE id = ?",
                [$profile->getId()],
            );
        $this->client->catchExceptions(true);
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
    }
    public function testBookerFromAnotherBandCannotReadAnAdministrativeProfile(): void
    {
        $target = $this->profile(
            $this->entityManager()->find(User::class, $this->userId),
            MemberKind::Musician,
        );
        $em = $this->entityManager();
        $band = new Band('Other band', new \App\Entity\TechnicalSheet()->setBandName('Other band'));
        $em->persist($band);
        $em->flush();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'outside-booker@example.test',
                'Other booker',
                'Another strong password 2026!',
                $band,
                BandRole::Booker,
            );
        $em->persist(new MemberProfile($band, $user, 'Other booker', 'Booker', MemberKind::Booker));
        $em->flush();
        $this->client->loginUser($user);
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/equipe/' . $target->getPublicId());
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.administrative-profile');
    }

    public function testCipherRejectsWrongKey(): void
    {
        $profile = $this->profile(
            $this->entityManager()->find(User::class, $this->userId),
            MemberKind::Musician,
        );
        $this->expectException(\RuntimeException::class);
        new ProfileCipher(str_repeat('cd', 32))->decrypt($profile);
    }
}
