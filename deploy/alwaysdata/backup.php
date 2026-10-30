<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

umask(0077);
$root = dirname(__DIR__, 2);

try {
    require $root.'/vendor/autoload.php';
    $contents = file_get_contents($root.'/.env.local');
    if (false === $contents) {
        throw new RuntimeException('Configuration illisible.');
    }
    $environment = new Dotenv()->parse($contents);
    if (
        ($environment['APP_ENV'] ?? null) !== 'prod'
        || is_file($root.'/.env.local.php')
        || is_file($root.'/.env.prod.local')
        || is_file($root.'/.env.prod')
    ) {
        throw new RuntimeException('Configuration de production inattendue.');
    }
    $databaseName = match (true) {
        ($environment['APP_DEMO_MODE'] ?? null) === '1'
            && ($environment['DATABASE_URL'] ?? null) === 'sqlite:///%kernel.project_dir%/var/demo.db' => 'demo.db',
        ($environment['APP_DEMO_MODE'] ?? null) === '0'
            && ($environment['DATABASE_URL'] ?? null) === 'sqlite:///%kernel.project_dir%/var/production.db' => 'production.db',
        default => throw new RuntimeException('La base ne correspond pas à cette installation.'),
    };
    if (!is_file($root.'/var/'.$databaseName) || is_link($root.'/var/'.$databaseName)) {
        throw new RuntimeException('Base de production absente ou liée à un autre fichier.');
    }

    $files = ['.env.local', 'public/.htaccess'];
    if ('demo.db' === $databaseName) {
        $files[] = 'var/private/.htpasswd';
        $files[] = 'var/private/demo-access.json';
    } elseif (is_file($root.'/var/private/.htpasswd')) {
        $files[] = 'var/private/.htpasswd';
    }
    if (is_file($root.'/var/private/deployment.json')) {
        $files[] = 'var/private/deployment.json';
    }
    $activationFiles = glob($root.'/var/private/activation-codes-*.json');
    if (false === $activationFiles) {
        throw new RuntimeException('Impossible de parcourir les fichiers privés.');
    }
    foreach ($activationFiles as $file) {
        $files[] = substr($file, strlen($root) + 1);
    }
    foreach ($files as $file) {
        if (!is_file($root.'/'.$file) || !is_readable($root.'/'.$file)) {
            throw new RuntimeException('Un fichier privé nécessaire à la sauvegarde est absent.');
        }
    }

    $directory =
        $root.'/var/private/backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700, true)) {
        throw new RuntimeException('Impossible de créer le dossier de sauvegarde.');
    }
    $database = new PDO('sqlite:'.$root.'/var/'.$databaseName);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA busy_timeout = 10000');
    $database->exec('VACUUM INTO '.$database->quote($directory.'/'.$databaseName));
    if (!chmod($directory.'/'.$databaseName, 0600)) {
        throw new RuntimeException('Impossible de protéger la copie de la base.');
    }
    $copy = new PDO('sqlite:'.$directory.'/'.$databaseName);
    $integrity = $copy->query('PRAGMA integrity_check');
    if (false === $integrity || 'ok' !== $integrity->fetchColumn()) {
        throw new RuntimeException('La copie de la base est invalide.');
    }
    foreach ($files as $file) {
        $target = $directory.'/'.basename($file);
        if (!copy($root.'/'.$file, $target) || !chmod($target, 0600)) {
            throw new RuntimeException('La sauvegarde des fichiers privés a échoué.');
        }
    }
    fwrite(STDOUT, "Sauvegarde créée : $directory\n");
    fwrite(STDOUT, "Elle contient la base et les accès privés, pas le code de l’application.\n");
} catch (Throwable) {
    fwrite(STDERR, "Sauvegarde incomplète ou impossible. N’envoie pas la mise à jour.\n");
    exit(1);
}
