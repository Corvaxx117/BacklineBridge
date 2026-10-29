<?php

declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\MemberProfile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProfileCipher
{
    public function __construct(
        #[Autowire('%env(PROFILE_DATA_KEY)%')] #[\SensitiveParameter] private string $key,
    ) {}

    private function decodedKey(): string
    {
        if (!extension_loaded('sodium') || !preg_match('/^[a-f0-9]{64}$/iD', $this->key)) {
            throw new \RuntimeException(
                'Configurer une clé PROFILE_DATA_KEY de 32 octets et activer Sodium avant de lire ou importer les données administratives.',
            );
        }
        return hex2bin($this->key);
    }

    public function encrypt(MemberProfile $profile, #[\SensitiveParameter] array $data): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return base64_encode(
            $nonce .
                sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $profile->getPublicId(),
                    $nonce,
                    $this->decodedKey(),
                ),
        );
    }

    public function decrypt(MemberProfile $profile): array
    {
        if ($profile->getAdministrativeCiphertext() === null) {
            return [];
        }
        $raw = base64_decode($profile->getAdministrativeCiphertext(), true);
        $size = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (
            $raw === false ||
            strlen($raw) < $size + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES
        ) {
            throw new \RuntimeException('Données administratives illisibles.');
        }
        $text = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($raw, $size),
            $profile->getPublicId(),
            substr($raw, 0, $size),
            $this->decodedKey(),
        );
        if ($text === false) {
            throw new \RuntimeException(
                'La clé ne permet pas de lire ces données administratives.',
            );
        }
        return json_decode($text, true, flags: JSON_THROW_ON_ERROR);
    }
}
