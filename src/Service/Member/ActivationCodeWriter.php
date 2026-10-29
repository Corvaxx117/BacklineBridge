<?php

declare(strict_types=1);

namespace App\Service\Member;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ActivationCodeWriter
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}
    /** @param list<array{name: string, code: string, expires: string}> $codes */
    public function write(#[\SensitiveParameter] array $codes): string
    {
        $directory = $this->projectDir . '/var/private';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de préparer les codes privés.');
        }
        $path = $directory . '/activation-codes-' . bin2hex(random_bytes(8)) . '.json';
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de créer le fichier privé.');
        }
        try {
            if (!chmod($path, 0600)) {
                throw new \RuntimeException('Impossible de protéger le fichier privé.');
            }
            $content = json_encode(
                ['activationPath' => '/premiere-connexion', 'codes' => $codes],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            if (fwrite($handle, $content) !== strlen($content)) {
                throw new \RuntimeException('Écriture des codes incomplète.');
            }
        } catch (\Throwable $error) {
            unlink($path);
            throw $error;
        } finally {
            fclose($handle);
        }
        return $path;
    }
}
