<?php

declare(strict_types=1);

namespace App\Dto\Notification;

final readonly class PushSubscriptionInput
{
    public function __construct(public string $endpoint, public string $publicKey, public string $authToken)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $endpoint = $data['endpoint'] ?? null;
        $keys = $data['keys'] ?? null;
        if (!is_string($endpoint) || !is_array($keys)
            || !is_string($keys['p256dh'] ?? null) || !is_string($keys['auth'] ?? null)
            || !self::allowedEndpoint($endpoint)
            || !self::validKey($keys['p256dh'], 65, true) || !self::validKey($keys['auth'], 16)
        ) {
            throw new \InvalidArgumentException('Les informations de cet appareil sont invalides.');
        }

        return new self($endpoint, $keys['p256dh'], $keys['auth']);
    }

    public static function allowedEndpoint(string $endpoint): bool
    {
        if (strlen($endpoint) > 2048 || false === filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }
        $url = parse_url($endpoint);
        if (false === $url || 'https' !== ($url['scheme'] ?? '')
            || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])
            || (isset($url['port']) && 443 !== $url['port']) || empty($url['path'])
        ) {
            return false;
        }
        $host = $url['host'] ?? '';

        // Aucune URL libre : le serveur contacte uniquement les relais des navigateurs.
        return in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com'], true)
            || 1 === preg_match('/^(?:[a-z0-9-]+\.)?web\.push\.apple\.com$/D', $host);
    }

    private static function validKey(string $key, int $length, bool $point = false): bool
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $key)) {
            return false;
        }
        $bytes = base64_decode(strtr($key, '-_', '+/'), true);

        return false !== $bytes && strlen($bytes) === $length && (!$point || "\x04" === $bytes[0]);
    }
}
