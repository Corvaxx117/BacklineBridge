<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Cache privé borné à 32 PDF de 2 Mo et une heure maximum. */
final readonly class PdfFileCache
{
    private const MAX_FILES = 32;
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const LIFETIME = 3600;

    public function __construct(
        #[Autowire('%kernel.cache_dir%/private/pdf')] private string $directory,
    ) {
    }

    /** @param \Closure(): string $generate */
    public function get(string $key, \Closure $generate): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new \InvalidArgumentException('Clé de cache PDF invalide.');
        }
        if (is_link($this->directory) || (!is_dir($this->directory) && !mkdir($this->directory, 0700, true))) {
            throw new \RuntimeException('Le cache PDF est indisponible.');
        }
        chmod($this->directory, 0700);
        $lockPath = $this->directory.'/.lock';
        if (is_link($lockPath)) {
            throw new \RuntimeException('Le verrou PDF est invalide.');
        }
        $lock = fopen($lockPath, 'c+b');
        if (false === $lock) {
            throw new \RuntimeException('Le cache PDF est indisponible.');
        }
        chmod($lockPath, 0600);
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Le cache PDF est occupé.');
            }
            $path = $this->directory.'/'.$key.'.pdf';
            if (is_link($path)) {
                throw new \RuntimeException('Le cache PDF est invalide.');
            }
            clearstatcache(true, $path);
            if (is_file($path) && filemtime($path) > time() - self::LIFETIME && filesize($path) <= self::MAX_BYTES) {
                $cached = file_get_contents($path);
                if (false !== $cached && str_starts_with($cached, '%PDF-')) {
                    return $cached;
                }
            }
            $pdf = $generate();
            if (strlen($pdf) > self::MAX_BYTES) {
                return $pdf;
            }
            $this->prune();
            $temporary = $path.'.tmp';
            if (is_link($temporary)) {
                throw new \RuntimeException('Le cache PDF est invalide.');
            }
            try {
                if (file_put_contents($temporary, $pdf) !== strlen($pdf) || !chmod($temporary, 0600) || !rename($temporary, $path)) {
                    throw new \RuntimeException('Impossible de conserver le PDF.');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }

            return $pdf;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function prune(): void
    {
        $files = [];
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if (!$file->isFile() || $file->isLink() || !preg_match('/^[a-f0-9]{64}\.pdf$/D', $file->getFilename())) {
                continue;
            }
            if ($file->getMTime() <= time() - self::LIFETIME) {
                unlink($file->getPathname());
            } else {
                $files[$file->getPathname()] = $file->getMTime();
            }
        }
        asort($files);
        while (count($files) >= self::MAX_FILES) {
            $oldest = array_key_first($files);
            unlink($oldest);
            unset($files[$oldest]);
        }
    }
}
