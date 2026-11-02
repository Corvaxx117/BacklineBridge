<?php

declare(strict_types=1);

namespace App\Tests\Integration\Deployment;

use PHPUnit\Framework\TestCase;

final class AssetCacheUpdateTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/bb-asset-cache-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/deploy/alwaysdata', 0700, true);
        mkdir($this->directory.'/public', 0700, true);
        foreach (['update-asset-cache.php', 'production.htaccess.template'] as $file) {
            copy(dirname(__DIR__, 3).'/deploy/alwaysdata/'.$file, $this->directory.'/deploy/alwaysdata/'.$file);
        }
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testUpdatePreservesDemoAuthenticationAndIsIdempotent(): void
    {
        $path = $this->directory.'/public/.htaccess';
        $current = "FallbackResource /index.php\nRequire all denied\nHeader always set Cache-Control \"private, no-store\"\nAuthType Basic\nAuthUserFile /private/fictif.auth\nRequire valid-user\nHeader set X-Custom fictif\n";
        file_put_contents($path, $current);
        self::assertSame(0, $this->runScript());
        $updated = file_get_contents($path);
        self::assertStringStartsWith($current, $updated);
        self::assertStringContainsString('max-age=31536000, immutable', $updated);
        self::assertStringContainsString('AuthUserFile /private/fictif.auth', $updated);
        self::assertSame(0, $this->runScript());
        self::assertSame($updated, file_get_contents($path));
        $backups = glob($this->directory.'/var/private/asset-cache-*');
        self::assertCount(1, $backups);
        self::assertSame($current, file_get_contents($backups[0].'/.htaccess'));
    }

    public function testIncompleteProtectionIsNeverOverwritten(): void
    {
        $path = $this->directory.'/public/.htaccess';
        file_put_contents($path, '# Configuration à vérifier');
        self::assertSame(1, $this->runScript());
        self::assertSame('# Configuration à vérifier', file_get_contents($path));
        self::assertDirectoryDoesNotExist($this->directory.'/var');
    }

    private function runScript(): int
    {
        $process = proc_open([PHP_BINARY, $this->directory.'/deploy/alwaysdata/update-asset-cache.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        foreach ($pipes as $pipe) {
            stream_get_contents($pipe);
            fclose($pipe);
        }

        return proc_close($process);
    }
}
