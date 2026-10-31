<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Band;
use App\Entity\BandDocument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class DocumentStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/documents')]
        private string $directory,
        #[Autowire(env: 'int:DOCUMENT_STORAGE_QUOTA_MB')]
        private int $quotaMegabytes,
    ) {
        if ($quotaMegabytes < 1 || $quotaMegabytes > 2048) {
            throw new \InvalidArgumentException('Le quota des documents doit être compris entre 1 et 2048 Mo.');
        }
    }

    public function getQuotaBytes(): int
    {
        return $this->quotaMegabytes * 1024 * 1024;
    }

    public function usedBytes(Band $band): int
    {
        $directory = $this->directory.'/'.$band->getId();
        if (!is_dir($directory)) {
            return 0;
        }
        $size = 0;
        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function exclusively(\Closure $operation): mixed
    {
        $this->ensureDirectory($this->directory);
        $lock = fopen($this->directory.'/.storage.lock', 'c+b');
        if (false === $lock) {
            throw new \RuntimeException('Le stockage des documents est indisponible.');
        }
        chmod($this->directory.'/.storage.lock', 0600);
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Le stockage des documents est occupé.');
            }

            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function store(Band $band, UploadedFile $file): string
    {
        $size = $file->getSize();
        if ($size > 10 * 1024 * 1024 || '%PDF-' !== file_get_contents($file->getPathname(), false, null, 0, 5)) {
            throw new \DomainException('Choisis un fichier PDF valide de 10 Mo maximum.');
        }
        if ($this->usedBytes($band) + $size > $this->getQuotaBytes()) {
            throw new \DomainException('Le quota de PDF du groupe est atteint. Retire un ancien PDF ou ajoute un lien externe.');
        }
        $directory = $this->directory.'/'.$band->getId();
        $this->ensureDirectory($directory);
        $key = bin2hex(random_bytes(16)).'.pdf';
        $file->move($directory, $key);
        if (!chmod($directory.'/'.$key, 0600)) {
            unlink($directory.'/'.$key);
            throw new \RuntimeException('Impossible de protéger le PDF.');
        }

        return $key;
    }

    public function path(BandDocument $document): string
    {
        $key = $document->getStorageKey();
        if (null === $key || 1 !== preg_match('/^[a-f0-9]{32}\.pdf$/D', $key)) {
            throw new \LogicException('Ce document ne contient pas de PDF.');
        }
        $path = $this->directory.'/'.$document->getBand()->getId().'/'.$key;
        if (is_link($path)) {
            throw new \RuntimeException('Le PDF ne doit pas être un lien symbolique.');
        }

        return $path;
    }

    public function discard(BandDocument $document): void
    {
        $path = $this->path($document);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Impossible de retirer le PDF du stockage.');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0700, true))) {
            throw new \RuntimeException('Impossible de préparer le stockage privé des documents.');
        }
    }
}
