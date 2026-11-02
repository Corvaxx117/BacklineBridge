<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $root = dirname(__DIR__, 2);
    $path = $root.'/public/.htaccess';
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('La protection du site doit déjà exister.');
    }
    $current = file_get_contents($path);
    $template = file_get_contents(__DIR__.'/production.htaccess.template');
    $pattern = '/# BEGIN BacklineBridge asset cache\R.*?# END BacklineBridge asset cache\R?/s';
    if (false === $current || false === $template || 1 !== preg_match($pattern, $template, $block)) {
        throw new RuntimeException('Protection ou modèle illisible.');
    }
    foreach (['FallbackResource /index.php', 'Require all denied', 'Cache-Control "private, no-store"'] as $protection) {
        if (!str_contains($current, $protection)) {
            throw new RuntimeException('La protection existante doit être vérifiée avant modification.');
        }
    }
    $count = preg_match_all($pattern, $current);
    if (false === $count || $count > 1 || (0 === $count && str_contains($current, '# BEGIN BacklineBridge asset cache'))) {
        throw new RuntimeException('La règle de cache existante est ambiguë.');
    }
    $updated = 1 === $count
        ? preg_replace_callback($pattern, static fn (): string => $block[0], $current)
        : rtrim($current)."\n\n".$block[0];
    if (null === $updated) {
        throw new RuntimeException('Impossible de préparer la règle de cache.');
    }
    if ($updated === $current) {
        echo "La règle de cache est déjà à jour.\n";
        exit;
    }
    $backup = $root.'/var/private/asset-cache-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
    if (!mkdir($backup, 0700, true) || file_put_contents($backup.'/.htaccess', $current) !== strlen($current) || !chmod($backup.'/.htaccess', 0600)) {
        throw new RuntimeException('Impossible de sauvegarder la protection existante.');
    }
    $temporary = $path.'.asset-cache-new';
    $handle = fopen($temporary, 'x');
    if (false === $handle) {
        throw new RuntimeException('Un fichier de préparation existe déjà ; vérifier son origine.');
    }
    try {
        $mode = fileperms($path);
        if (false === $mode || !chmod($temporary, $mode & 0777) || fwrite($handle, $updated) !== strlen($updated)) {
            throw new RuntimeException('Impossible de préparer la protection.');
        }
        fclose($handle);
        $handle = null;
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Impossible de remplacer la règle de cache.');
        }
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
    echo "Règle de cache mise à jour ; protections existantes conservées.\n";
    echo 'Sauvegarde : '.substr($backup, strlen($root) + 1)."/.htaccess\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
