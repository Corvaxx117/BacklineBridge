<?php

declare(strict_types=1);

namespace App\Tests\Functional\Theme;

use App\Tests\VenueTestCase;

final class ThemeLayoutTest extends VenueTestCase
{
    public function testThemeBootstrapPrecedesStylesAndTheChoiceIsAvailableWhileLoggedIn(): void
    {
        $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        $this->assertSharedTheme();
        $html = $this->client->getResponse()->getContent();
        self::assertLessThan(strpos($html, '/styles/tokens.css'), strpos($html, '/scripts/theme.js'));
        self::assertLessThan(strpos($html, '/styles/app.css'), strpos($html, '/styles/tokens.css'));
        self::assertSelectorNotExists('script[src="/scripts/theme.js"][defer]');
    }

    public function testTheLoginPageHasTheSameThemeControl(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/connexion');
        self::assertResponseIsSuccessful();
        $this->assertSharedTheme();
    }

    public function testTheGuestWorkspaceHasTheSameThemeWithoutInternalNavigation(): void
    {
        $this->enter($this->issue()->token);
        $this->assertSharedTheme();
        self::assertSelectorNotExists('#primary-navigation');
    }

    private function assertSharedTheme(): void
    {
        self::assertSelectorCount(1, '[data-theme-control]');
        self::assertSelectorCount(1, 'button[data-theme-toggle][role="switch"][aria-checked]');
        self::assertSelectorCount(1, 'button[data-theme-auto][aria-pressed]');
        self::assertSelectorNotExists('header [data-theme-control]');
        self::assertSelectorNotExists('select[data-theme-choice]');
        self::assertSelectorExists('link[href="/styles/tokens.css"]');
        self::assertSelectorExists('script[src="/scripts/theme.js"]');
    }
}
