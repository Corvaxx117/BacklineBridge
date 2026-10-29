<?php

declare(strict_types=1);

namespace App\Tests\Integration\Deployment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlwaysdataBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . '/backlinebridge-backup-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/deploy/alwaysdata', 0700, true);
        mkdir($this->directory . '/public', 0700);
        mkdir($this->directory . '/var/private', 0700, true);
        copy(
            dirname(__DIR__, 3) . '/deploy/alwaysdata/backup.php',
            $this->directory . '/deploy/alwaysdata/backup.php',
        );
        symlink(dirname(__DIR__, 3) . '/vendor', $this->directory . '/vendor');
        file_put_contents(
            $this->directory . '/.env.local',
            "APP_ENV=prod\nAPP_DEMO_MODE=1\nDATABASE_URL=sqlite:///%kernel.project_dir%/var/demo.db\nAPP_SECRET=backup-test-only\n",
        );
        foreach (
            [
                'public/.htaccess',
                'var/private/.htpasswd',
                'var/private/demo-access.json',
                'var/private/activation-codes-test.json',
            ]
            as $file
        ) {
            file_put_contents($this->directory . '/' . $file, 'private-backup-test');
        }
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink()
                ? rmdir($file->getPathname())
                : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testBackupIncludesUncheckpointedDataAndSecretsWithoutChangingTheOriginal(): void
    {
        $database = new \PDO('sqlite:' . $this->directory . '/var/demo.db');
        $database->exec('PRAGMA journal_mode = WAL');
        $database->exec('CREATE TABLE notes (value TEXT)');
        $database->exec("INSERT INTO notes VALUES ('saved-before-update')");
        $database->beginTransaction();
        $database->query('SELECT * FROM notes')->fetchAll();

        for ($i = 1; $i <= 2; ++$i) {
            [$status, $output] = $this->backup();
            self::assertSame(0, $status, $output);
            self::assertStringNotContainsString('private-backup-test', $output);
            self::assertStringNotContainsString('backup-test-only', $output);
            $folders = glob($this->directory . '/var/private/backups/*');
            self::assertCount($i, $folders);
            foreach ($folders as $folder) {
                self::assertSame(0700, fileperms($folder) & 0777);
                $copy = new \PDO('sqlite:' . $folder . '/demo.db');
                self::assertSame('ok', $copy->query('PRAGMA integrity_check')->fetchColumn());
                self::assertSame(
                    'saved-before-update',
                    $copy->query('SELECT value FROM notes')->fetchColumn(),
                );
                foreach (
                    [
                        'demo.db',
                        '.env.local',
                        '.htaccess',
                        '.htpasswd',
                        'demo-access.json',
                        'activation-codes-test.json',
                    ]
                    as $file
                ) {
                    self::assertSame(0600, fileperms($folder . '/' . $file) & 0777);
                }
                self::assertSame(
                    file_get_contents($this->directory . '/.env.local'),
                    file_get_contents($folder . '/.env.local'),
                );
                self::assertSame(
                    'private-backup-test',
                    file_get_contents($folder . '/activation-codes-test.json'),
                );
            }
        }
        self::assertSame(
            'saved-before-update',
            $database->query('SELECT value FROM notes')->fetchColumn(),
        );
    }

    #[DataProvider('invalidConfigurations')]
    public function testBackupRefusesAnUnexpectedConfiguration(string $configuration): void
    {
        file_put_contents($this->directory . '/.env.local', $configuration);
        [$status] = $this->backup();
        self::assertSame(1, $status);
        self::assertDirectoryDoesNotExist($this->directory . '/var/private/backups');
    }

    public static function invalidConfigurations(): iterable
    {
        yield 'development database' => [
            "APP_ENV=dev\nAPP_DEMO_MODE=0\nDATABASE_URL=sqlite:///%kernel.project_dir%/var/backlinebridge.db\n",
        ];
        yield 'different production database' => [
            "APP_ENV=prod\nAPP_DEMO_MODE=1\nDATABASE_URL=sqlite:///%kernel.project_dir%/var/other.db\n",
        ];
        yield 'missing database' => [
            "APP_ENV=prod\nAPP_DEMO_MODE=1\nDATABASE_URL=sqlite:///%kernel.project_dir%/var/demo.db\n",
        ];
    }

    private function backup(): array
    {
        $process = proc_open(
            [PHP_BINARY, $this->directory . '/deploy/alwaysdata/backup.php'],
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
