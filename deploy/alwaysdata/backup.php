<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

umask(0077);
$root = dirname(__DIR__, 2);

try {
    require $root . '/vendor/autoload.php';
    $environment = new Dotenv()->parse(file_get_contents($root . '/.env.local'));
    if (
        ($environment['APP_ENV'] ?? null) !== 'prod' ||
        ($environment['APP_DEMO_MODE'] ?? null) !== '1' ||
        ($environment['DATABASE_URL'] ?? null) !== 'sqlite:///%kernel.project_dir%/var/demo.db' ||
        is_file($root . '/.env.local.php') ||
        is_file($root . '/.env.prod.local') ||
        !is_file($root . '/var/demo.db')
    ) {
        throw new RuntimeException('Cette commande est réservée à la démonstration hébergée.');
    }

    $files = [
        '.env.local',
        'public/.htaccess',
        'var/private/.htpasswd',
        'var/private/demo-access.json',
    ];
    foreach (glob($root . '/var/private/activation-codes-*.json') as $file) {
        $files[] = substr($file, strlen($root) + 1);
    }
    foreach ($files as $file) {
        if (!is_file($root . '/' . $file) || !is_readable($root . '/' . $file)) {
            throw new RuntimeException('Un fichier privé nécessaire à la sauvegarde est absent.');
        }
    }

    $directory =
        $root . '/var/private/backups/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700, true)) {
        throw new RuntimeException('Impossible de créer le dossier de sauvegarde.');
    }
    $database = new PDO('sqlite:' . $root . '/var/demo.db');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA busy_timeout = 10000');
    $database->exec('VACUUM INTO ' . $database->quote($directory . '/demo.db'));
    chmod($directory . '/demo.db', 0600);
    foreach ($files as $file) {
        $target = $directory . '/' . basename($file);
        if (!copy($root . '/' . $file, $target) || !chmod($target, 0600)) {
            throw new RuntimeException('La sauvegarde des fichiers privés a échoué.');
        }
    }
    fwrite(STDOUT, "Sauvegarde créée : $directory\n");
    fwrite(STDOUT, "Elle contient la base et les accès privés, pas le code de l’application.\n");
} catch (Throwable) {
    fwrite(STDERR, "Sauvegarde incomplète ou impossible. N’envoie pas la mise à jour.\n");
    exit(1);
}
