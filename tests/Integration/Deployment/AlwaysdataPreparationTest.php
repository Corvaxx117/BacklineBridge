<?php

declare(strict_types=1);

namespace App\Tests\Integration\Deployment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

final class AlwaysdataPreparationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . '/backlinebridge-deployment-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/deploy/alwaysdata', 0700, true);
        mkdir($this->directory . '/public', 0700);
        foreach (['prepare.php', 'private.htaccess.template'] as $file) {
            copy(
                dirname(__DIR__, 3) . '/deploy/alwaysdata/' . $file,
                $this->directory . '/deploy/alwaysdata/' . $file,
            );
        }
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testFreshConfigurationCreatesDistinctSecretsAndProtectsEveryPage(): void
    {
        [$status, $output] = $this->prepare('demo-test');
        self::assertSame(0, $status, $output);
        $environment = new Dotenv()->parse(file_get_contents($this->directory . '/.env.local'));
        self::assertSame('prod', $environment['APP_ENV']);
        self::assertSame('0', $environment['APP_DEBUG']);
        self::assertSame('1', $environment['APP_DEMO_MODE']);
        self::assertSame(
            'sqlite:///%kernel.project_dir%/var/demo.db',
            $environment['DATABASE_URL'],
        );
        self::assertSame('', $environment['BANDSINTOWN_APP_ID']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $environment['PROFILE_DATA_KEY']);
        self::assertNotSame($environment['APP_SECRET'], $environment['PROFILE_DATA_KEY']);
        self::assertStringNotContainsString($environment['APP_SECRET'], $output);
        self::assertStringNotContainsString($environment['PROFILE_DATA_KEY'], $output);
        self::assertSame(
            1,
            preg_match('#' . $environment['APP_TRUSTED_HOST'] . '#D', 'demo-test.alwaysdata.net'),
        );
        self::assertSame(
            0,
            preg_match(
                '#' . $environment['APP_TRUSTED_HOST'] . '#D',
                'demo-test.alwaysdata.net.example.org',
            ),
        );

        $credentials = json_decode(
            file_get_contents($this->directory . '/var/private/demo-access.json'),
            true,
        );
        self::assertSame('https://demo-test.alwaysdata.net', $credentials['url']);
        self::assertGreaterThanOrEqual(32, strlen($credentials['password']));
        self::assertStringNotContainsString($credentials['password'], $output);
        $hash = substr(trim(file_get_contents($this->directory . '/var/private/.htpasswd')), 5);
        self::assertTrue(password_verify($credentials['password'], $hash));
        foreach (['.env.local', 'var/private/.htpasswd', 'var/private/demo-access.json'] as $file) {
            self::assertSame(0600, fileperms($this->directory . '/' . $file) & 0777);
        }
        $protection = file_get_contents($this->directory . '/public/.htaccess');
        self::assertStringContainsString('Require valid-user', $protection);
        self::assertStringContainsString(
            '<If "req(\'X-Forwarded-Proto\') != \'https\'">',
            $protection,
        );
        self::assertStringContainsString(
            'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on',
            $protection,
        );
        self::assertStringContainsString('FallbackResource /index.php', $protection);
        self::assertStringContainsString($this->directory . '/var/private/.htpasswd', $protection);
        self::assertStringNotContainsString('{{AUTH_USER_FILE}}', $protection);
        self::assertStringNotContainsString($credentials['password'], $protection);
        self::assertFileDoesNotExist($this->directory . '/var/demo.db');
    }

    #[DataProvider('existingConfigurations')]
    public function testExistingConfigurationIsNeverOverwritten(string $file): void
    {
        $path = $this->directory . '/' . $file;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, 'keep-this-existing-data');
        [$status] = $this->prepare('demo-test');
        self::assertSame(1, $status);
        self::assertSame('keep-this-existing-data', file_get_contents($path));
        self::assertFileDoesNotExist($this->directory . '/var/private/demo-access.json');
    }

    public static function existingConfigurations(): iterable
    {
        foreach (
            [
                '.env.local',
                '.env.local.php',
                '.env.prod.local',
                '.env.dev.local',
                'var/backlinebridge.db',
                'var/demo.db',
                'public/.htaccess',
            ]
            as $file
        ) {
            yield $file => [$file];
        }
    }

    public function testRepeatingPreparationPreservesSecretsAndCredentials(): void
    {
        self::assertSame(0, $this->prepare('demo-test')[0]);
        $environment = file_get_contents($this->directory . '/.env.local');
        $access = file_get_contents($this->directory . '/var/private/demo-access.json');
        self::assertSame(1, $this->prepare('demo-test')[0]);
        self::assertSame($environment, file_get_contents($this->directory . '/.env.local'));
        self::assertSame(
            $access,
            file_get_contents($this->directory . '/var/private/demo-access.json'),
        );
    }

    public function testAnInvalidAccountCreatesNoConfiguration(): void
    {
        self::assertSame(1, $this->prepare('../other-account')[0]);
        self::assertDirectoryDoesNotExist($this->directory . '/var');
        self::assertFileDoesNotExist($this->directory . '/public/.htaccess');
    }

    /** @return array{int, string} */
    private function prepare(string $account): array
    {
        $process = proc_open(
            [PHP_BINARY, $this->directory . '/deploy/alwaysdata/prepare.php', $account],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }
}
