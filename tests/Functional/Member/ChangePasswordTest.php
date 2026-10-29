<?php

declare(strict_types=1);

namespace App\Tests\Functional\Member;

use App\Entity\User;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChangePasswordTest extends DatabaseTestCase
{
    private const string URI = '/mon-compte/mot-de-passe';
    private const string CURRENT_PASSWORD = 'BacklineBridge test password 2026!';

    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testChangingThePasswordKeepsTheCurrentSessionAndAllowsLoginWithTenCharacters(): void
    {
        $this->client->request('GET', self::URI);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'no-store',
            $this->client->getResponse()->headers->get('Cache-Control'),
        );
        self::assertSelectorTextContains(
            '#change_password_password_first_help',
            '10 caractères minimum',
        );
        $before = $this->passwordHash();
        $this->submit();
        self::assertResponseRedirects(self::URI, 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', 'Ton mot de passe a été modifié.');
        self::assertNotSame($before, $this->passwordHash());

        $this->client->restart();
        $form = $this->client->request('GET', '/connexion')->selectButton('Se connecter')->form();
        $this->client->submit($form, [
            '_username' => 'owner@example.test',
            '_password' => 'Corail!94x',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.session-name', 'Responsable de test');

        $user = $this->entityManager()->find(User::class, $this->userId);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'Corail!94x'));
        self::assertFalse($hasher->isPasswordValid($user, self::CURRENT_PASSWORD));
    }

    #[DataProvider('invalidChanges')]
    public function testInvalidRequestsNeverChangeThePassword(array $changes): void
    {
        $before = $this->passwordHash();
        $this->submit($changes);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->passwordHash());
        self::assertSelectorExists('form[name="change_password"] ul li');
        self::assertSelectorNotExists('input[type="password"][value]:not([value=""])');
    }

    public static function invalidChanges(): iterable
    {
        yield 'incorrect current password' => [['currentPassword' => 'Wrong password 2026!']];
        yield 'empty current password' => [['currentPassword' => '']];
        yield 'empty new password' => [['password' => ['first' => '', 'second' => '']]];
        yield 'different confirmation' => [['password' => ['second' => 'Different!94x']]];
        yield 'too short' => [['password' => ['first' => 'Corai!94x', 'second' => 'Corai!94x']]];
        yield 'without uppercase' => [
            ['password' => ['first' => 'corail!94x', 'second' => 'corail!94x']],
        ];
        yield 'without digit' => [
            ['password' => ['first' => 'Corail!xxx', 'second' => 'Corail!xxx']],
        ];
        yield 'without symbol' => [
            ['password' => ['first' => 'Corail194xx', 'second' => 'Corail194xx']],
        ];
        yield 'invalid csrf' => [['_token' => 'invalid']];
        yield 'forged account' => [['userId' => 999]];
    }

    public function testAnonymousVisitorsCannotOpenOrSubmitTheForm(): void
    {
        $this->client->restart();
        $this->client->catchExceptions(true);
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, self::URI);
            self::assertResponseRedirects();
            self::assertSame(
                '/connexion',
                parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH),
            );
        }
    }

    public function testRepeatedIncorrectCurrentPasswordsAreLimited(): void
    {
        $before = $this->passwordHash();
        for ($i = 0; $i < 6; ++$i) {
            $this->submit(['currentPassword' => 'Wrong password 2026!']);
        }
        self::assertResponseStatusCodeSame(429);
        self::assertSame($before, $this->passwordHash());
    }

    private function submit(array $changes = []): void
    {
        $form = $this->client
            ->request('GET', self::URI)
            ->filter('form[name="change_password"]')
            ->form();
        $values = $form->getPhpValues();
        $values['change_password']['currentPassword'] = self::CURRENT_PASSWORD;
        $values['change_password']['password'] = [
            'first' => 'Corail!94x',
            'second' => 'Corail!94x',
        ];
        $values['change_password'] = array_replace_recursive($values['change_password'], $changes);
        $this->client->request('POST', self::URI, $values);
    }

    private function passwordHash(): string
    {
        return $this->entityManager()
            ->getConnection()
            ->fetchOne('SELECT password FROM app_user WHERE id = ?', [$this->userId]);
    }
}
