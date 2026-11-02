<?php

declare(strict_types=1);

namespace App\Service\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class PrivateCacheFactory
{
    public function create(string $namespace, int $lifetime, string $directory): FilesystemAdapter
    {
        if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0700, true))) {
            throw new \RuntimeException('Le cache privé est indisponible.');
        }
        if (!chmod($directory, 0700)) {
            throw new \RuntimeException('Impossible de protéger le cache privé.');
        }

        return new FilesystemAdapter($namespace, $lifetime, $directory);
    }
}
