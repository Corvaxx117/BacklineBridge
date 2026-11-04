<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use Minishlink\WebPush\VAPID;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PushConfiguration
{
    /** @var array{subject: string, publicKey: string, privateKey: string}|null */
    private ?array $keys = null;

    public function __construct(
        #[Autowire('%env(bool:WEB_PUSH_ENABLED)%')] private readonly bool $enabled,
        #[Autowire('%env(WEB_PUSH_KEY_FILE)%')] private readonly string $keyFile,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%env(bool:APP_DEMO_MODE)%')] private readonly bool $demoMode,
    ) {
    }

    public function keyFile(): string
    {
        return str_starts_with($this->keyFile, '/') ? $this->keyFile : $this->projectDir.'/'.$this->keyFile;
    }

    public function available(): bool
    {
        if (!$this->enabled || $this->demoMode) {
            return false;
        }
        try {
            $this->credentials();

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function publicKey(): string
    {
        return $this->credentials()['publicKey'];
    }

    /** @return array{subject: string, publicKey: string, privateKey: string} */
    public function credentials(): array
    {
        if (null !== $this->keys) {
            return $this->keys;
        }
        $path = $this->keyFile();
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Les clés de notification ne sont pas configurées.');
        }
        try {
            $contents = file_get_contents($path);
            $keys = false === $contents ? null : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($keys)
                || !is_string($keys['subject'] ?? null)
                || !is_string($keys['publicKey'] ?? null)
                || !is_string($keys['privateKey'] ?? null)
            ) {
                throw new \RuntimeException();
            }
            VAPID::validate($keys);
            $this->keys = [
                'subject' => $keys['subject'], 'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ];

            return $this->keys;
        } catch (\Throwable) {
            // Une erreur ne doit jamais révéler le contenu du fichier privé.
            throw new \RuntimeException('Le fichier de clés de notification est invalide.');
        }
    }
}
