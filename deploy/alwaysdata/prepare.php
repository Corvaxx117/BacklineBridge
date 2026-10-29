<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

/** Crée un fichier privé sans jamais écraser une configuration existante. */
function writePrivateFile(string $path, #[SensitiveParameter] string $contents): void
{
    $handle = fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('Impossible de créer un fichier privé.');
    }

    try {
        if (!chmod($path, 0600) || fwrite($handle, $contents) !== strlen($contents)) {
            throw new RuntimeException('Impossible de protéger ou écrire un fichier privé.');
        }
    } finally {
        fclose($handle);
    }
}

try {
    if (count($argv) !== 2 || !preg_match('/^(?=.{1,63}$)[a-z0-9]+(?:-[a-z0-9]+)*$/D', $argv[1])) {
        throw new InvalidArgumentException(
            'Usage : php deploy/alwaysdata/prepare.php nom-du-compte',
        );
    }
    if (!extension_loaded('sodium')) {
        throw new RuntimeException('Activer Sodium avant de préparer la démonstration.');
    }

    $projectDir = dirname(__DIR__, 2);
    $account = $argv[1];
    $host = $account . '.alwaysdata.net';

    // Refuser un dossier déjà utilisé empêche de remplacer ses clés ou sa base réelle.
    foreach (
        [
            '.env.local',
            '.env.local.php',
            '.env.prod.local',
            '.env.dev.local',
            'var/private',
            'var/backlinebridge.db',
            'var/demo.db',
            'public/.htaccess',
            'public/.htaccess.new',
        ]
        as $existing
    ) {
        if (file_exists($projectDir . '/' . $existing) || is_link($projectDir . '/' . $existing)) {
            throw new RuntimeException(
                'Dossier déjà configuré : aucune modification effectuée. Utiliser un dossier neuf.',
            );
        }
    }

    $template = file_get_contents(__DIR__ . '/private.htaccess.template');
    if ($template === false || substr_count($template, '{{AUTH_USER_FILE}}') !== 1) {
        throw new RuntimeException('Modèle de protection introuvable ou invalide.');
    }

    umask(0077);
    $privateDir = $projectDir . '/var/private';
    if (!mkdir($privateDir, 0700, true)) {
        throw new RuntimeException('Impossible de préparer le répertoire privé.');
    }

    // Garder le site fermé, même si une étape suivante échoue.
    writePrivateFile($projectDir . '/public/.htaccess', "Require all denied\n");
    $password = bin2hex(random_bytes(18));
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    writePrivateFile($privateDir . '/.htpasswd', 'demo:' . $hash . "\n");
    writePrivateFile(
        $privateDir . '/demo-access.json',
        json_encode(
            ['url' => 'https://' . $host, 'username' => 'demo', 'password' => $password],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n",
    );

    $environment = [
        'APP_ENV=prod',
        'APP_DEBUG=0',
        'APP_SECRET=' . bin2hex(random_bytes(32)),
        'PROFILE_DATA_KEY=' . bin2hex(random_bytes(32)),
        'APP_DEMO_MODE=1',
        "APP_TRUSTED_HOST='^" . preg_quote($host, '#') . "$'",
        'DEFAULT_URI=https://' . $host,
        'DATABASE_URL="sqlite:///%kernel.project_dir%/var/demo.db"',
        'APP_SHARE_DIR=var/share',
        'BANDSINTOWN_BAND_ID=',
        'BANDSINTOWN_ARTIST_ID=',
        'BANDSINTOWN_APP_ID=',
    ];
    writePrivateFile($projectDir . '/.env.local', implode("\n", $environment) . "\n");

    $authPath = addcslashes($privateDir . '/.htpasswd', '\\"');
    $protection = str_replace('{{AUTH_USER_FILE}}', $authPath, $template);
    $temporaryProtection = $projectDir . '/public/.htaccess.new';
    writePrivateFile($temporaryProtection, $protection);
    if (!rename($temporaryProtection, $projectDir . '/public/.htaccess')) {
        throw new RuntimeException('Impossible d’installer la protection. Le site reste fermé.');
    }

    echo "Démonstration configurée en production, avec une base séparée.\n";
    echo "Accès initial : var/private/demo-access.json (à consulter en privé).\n";
    echo "Appliquer ensuite les migrations et importer uniquement les données de démonstration.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
