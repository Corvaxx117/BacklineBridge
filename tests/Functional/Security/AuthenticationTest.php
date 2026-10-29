<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthenticationTest extends DatabaseTestCase
{
    protected bool $authenticate = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    #[DataProvider('protectedPages')]
    public function testAnonymousVisitorsAreSentToLogin(string $uri, string $method): void
    {
        $this->client->request($method, $uri);

        self::assertResponseRedirects();
        self::assertSame(
            '/connexion',
            parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH),
        );
    }

    public static function protectedPages(): iterable
    {
        yield ['/', 'GET'];
        yield ['/concerts', 'GET'];
        yield ['/concerts/new', 'GET'];
        yield ['/concerts/new', 'POST'];
        yield ['/sources/new', 'POST'];
        yield ['/sources/1/edit', 'POST'];
    }

    public function testValidCredentialsUseTheNormalizedEmailAndCreateASession(): void
    {
        $this->submitLogin('OWNER@EXAMPLE.TEST', 'BacklineBridge test password 2026!');

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.session-name', 'Responsable de test');
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'JU VOICE');
    }

    #[DataProvider('invalidCredentials')]
    public function testWrongCredentialsAndMissingCsrfNeverCreateASession(
        string $email,
        string $password,
        bool $csrf,
    ): void {
        $this->submitLogin($email, $password, $csrf);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Connexion impossible');
        self::assertSelectorNotExists('.logout-form');
        $this->client->request('GET', '/concerts');
        self::assertResponseRedirects();
    }

    public static function invalidCredentials(): iterable
    {
        yield 'wrong password' => ['owner@example.test', 'Wrong password 2026!', true];
        yield 'unknown email' => [
            'unknown@example.test',
            'BacklineBridge test password 2026!',
            true,
        ];
        yield 'missing csrf' => ['owner@example.test', 'BacklineBridge test password 2026!', false];
    }

    public function testLoginIsThrottledAfterRepeatedFailuresEvenWithACorrectPassword(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->submitLogin('owner@example.test', 'Wrong password 2026!');
        }
        $this->submitLogin('owner@example.test', 'BacklineBridge test password 2026!');
        $this->client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Connexion impossible');
        $this->client->request('GET', '/concerts');
        self::assertResponseRedirects();
    }

    public function testLogoutRequiresPostAndCsrfThenInvalidatesTheSession(): void
    {
        $this->client->loginUser($this->entityManager()->find(User::class, $this->userId));
        $this->client->request('GET', '/deconnexion');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/deconnexion');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('.logout-form')->form());
        self::assertResponseRedirects();
        $this->client->request('GET', '/concerts');
        self::assertResponseRedirects();
        self::assertSame(
            '/connexion',
            parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH),
        );
    }

    private function submitLogin(string $email, string $password, bool $withCsrf = true): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $form = $crawler->filter('form[name="login"]')->form();
        $values = $form->getPhpValues();
        $values['_username'] = $email;
        $values['_password'] = $password;
        if (!$withCsrf) {
            unset($values['_csrf_token']);
        }
        $this->client->request('POST', '/connexion', $values);
    }
}
