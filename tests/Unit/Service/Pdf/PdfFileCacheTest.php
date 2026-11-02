<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pdf;

use App\Service\Pdf\PdfFileCache;
use PHPUnit\Framework\TestCase;

final class PdfFileCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/bb-pdf-cache-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_file($this->directory.'/.lock')) {
            unlink($this->directory.'/.lock');
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testCacheReusesPdfAndRegeneratesExpiredOrCorruptContent(): void
    {
        $cache = new PdfFileCache($this->directory);
        $key = hash('sha256', 'concert');
        $calls = 0;
        $generate = static function () use (&$calls): string { return '%PDF-'.++$calls; };
        self::assertSame('%PDF-1', $cache->get($key, $generate));
        self::assertSame('%PDF-1', $cache->get($key, $generate));
        $path = $this->directory.'/'.$key.'.pdf';
        self::assertSame(0600, fileperms($path) & 0777);
        touch($path, time() - 3601);
        self::assertSame('%PDF-2', $cache->get($key, $generate));
        file_put_contents($path, 'Fichier invalide');
        self::assertSame('%PDF-3', $cache->get($key, $generate));
        self::assertSame('%PDF-4', $cache->get(hash('sha256', 'nouveau dossier'), $generate));
    }

    public function testFileCountAndIndividualSizeStayBounded(): void
    {
        $cache = new PdfFileCache($this->directory);
        for ($i = 0; $i < 40; ++$i) {
            $cache->get(hash('sha256', (string) $i), static fn (): string => '%PDF-fictif');
        }
        self::assertCount(32, glob($this->directory.'/*.pdf'));
        $key = hash('sha256', 'trop volumineux');
        $large = '%PDF-'.str_repeat('x', 2 * 1024 * 1024);
        self::assertSame($large, $cache->get($key, static fn (): string => $large));
        self::assertFileDoesNotExist($this->directory.'/'.$key.'.pdf');
    }
}
