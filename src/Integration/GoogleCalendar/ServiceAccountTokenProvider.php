<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar;

use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ServiceAccountTokenProvider implements GoogleAccessTokenProvider
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly GoogleCalendarConfiguration $configuration,
        #[Autowire('%kernel.project_dir%/public')] private readonly string $publicDirectory,
    ) {
    }

    public function token(): string
    {
        if (null !== $this->accessToken) {
            return $this->accessToken;
        }
        $key = $this->credentials();
        try {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/calendar.events',
                $key,
            );
            $token = $credentials->fetchAuthToken(HttpHandlerFactory::build(new Client([
                'connect_timeout' => 3,
                'timeout' => 8,
                'allow_redirects' => false,
            ]), false));
            if (!is_string($token['access_token'] ?? null) || '' === $token['access_token']) {
                throw new \UnexpectedValueException();
            }

            return $this->accessToken = $token['access_token'];
        } catch (\Throwable) {
            // Ni réponse Google ni exception précédente : elles peuvent contenir des secrets.
            throw new GoogleCalendarUnavailable('La connexion à Google Agenda a échoué. Vérifie la clé du compte de service.');
        }
    }

    /** @return array{client_email: string, private_key: string, private_key_id: string} */
    private function credentials(): array
    {
        $path = realpath($this->configuration->credentialsFile());
        $public = realpath($this->publicDirectory);
        if (
            false === $path
            || !is_file($path)
            || !is_readable($path)
            || false === $public
            || str_starts_with($path, $public.DIRECTORY_SEPARATOR)
            || ($size = filesize($path)) === false
            || $size > 65536
        ) {
            throw new GoogleCalendarUnavailable('La clé Google doit être un fichier privé lisible, en dehors du dossier public.');
        }
        try {
            $content = file_get_contents($path);
            $data = json_decode(false === $content ? '' : $content, true, 16, JSON_THROW_ON_ERROR);
            if (
                !is_array($data)
                || ($data['type'] ?? null) !== 'service_account'
                || !is_string($data['client_email'] ?? null)
                || 1 !== preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.iam\.gserviceaccount\.com$/D', $data['client_email'])
                || !is_string($data['private_key'] ?? null)
                || !str_starts_with($data['private_key'], '-----BEGIN PRIVATE KEY-----')
                || !is_string($data['private_key_id'] ?? null)
                || 1 !== preg_match('/^[a-f0-9]{16,128}$/Di', $data['private_key_id'])
                || ($data['token_uri'] ?? null) !== 'https://oauth2.googleapis.com/token'
            ) {
                throw new \UnexpectedValueException();
            }

            // Ne transmettre à la bibliothèque que les champs attendus, sans URL fournie par le fichier.
            return [
                'client_email' => $data['client_email'],
                'private_key' => $data['private_key'],
                'private_key_id' => $data['private_key_id'],
            ];
        } catch (\Throwable) {
            throw new GoogleCalendarUnavailable('Le fichier de clé Google est invalide. Utilise la clé JSON d’un compte de service.');
        }
    }
}
