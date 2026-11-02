<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 2);
if (PHP_SAPI !== 'cli-server' || 'browser' !== getenv('APP_ENV')) {
    http_response_code(404);
    exit;
}
$url = getenv('DATABASE_URL');
if (!is_string($url) || !str_starts_with($url, 'sqlite:///'.$root.'/var/browser/backlinebridge-browser-')) {
    throw new RuntimeException('Le serveur navigateur exige une base fictive dédiée.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root.'/public'.rawurldecode($path ?: '/'));
if (false !== $file && str_starts_with($file, $root.'/public/') && is_file($file)) {
    return false;
}
require $root.'/vendor/autoload.php';
foreach (['APP_ENV', 'APP_DEBUG', 'APP_SECRET', 'PROFILE_DATA_KEY', 'DATABASE_URL', 'APP_DEMO_MODE', 'GOOGLE_CALENDAR_BAND_ID'] as $name) {
    $_ENV[$name] = $_SERVER[$name] = getenv($name);
}
(new Dotenv())->bootEnv($root.'/.env');
$kernel = new Kernel('browser', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
