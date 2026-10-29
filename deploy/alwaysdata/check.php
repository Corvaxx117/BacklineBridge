<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$failed = false;
$check = static function (string $label, bool $success) use (&$failed): void {
    printf("[%s] %s\n", $success ? 'OK' : 'ERREUR', $label);
    $failed = $failed || !$success;
};

$check(
    'PHP 8.4.1 ou supérieur dans la branche 8.4',
    PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 && PHP_VERSION_ID >= 80401,
);

foreach (
    ['ctype', 'iconv', 'sodium', 'pdo_sqlite', 'dom', 'xml', 'mbstring', 'intl']
    as $extension
) {
    $check('Extension ' . $extension, extension_loaded($extension));
}

if (extension_loaded('pdo_sqlite')) {
    try {
        $database = new PDO('sqlite::memory:');
        $database->exec('PRAGMA foreign_keys = ON');
        $check(
            'Contraintes SQLite actives',
            (int) $database->query('PRAGMA foreign_keys')->fetchColumn() === 1,
        );
    } catch (Throwable) {
        $check('Connexion SQLite', false);
    }
}

if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
    $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt('demo', 'check', $nonce, $key);
    $check(
        'Chiffrement et déchiffrement des profils',
        sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, 'check', $nonce, $key) ===
            'demo',
    );
    sodium_memzero($key);
} else {
    $check('Primitive de chiffrement des profils disponible', false);
}

$projectDir = dirname(__DIR__, 2);
$check('Racine du projet accessible en écriture', is_writable($projectDir));
$check(
    'Dossier public accessible en écriture pour sa protection',
    is_writable($projectDir . '/public'),
);
$check('Dépendances installées', is_file($projectDir . '/vendor/autoload.php'));

exit($failed ? 1 : 0);
