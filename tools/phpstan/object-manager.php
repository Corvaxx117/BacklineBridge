<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
new Dotenv()->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new Kernel('test', true);
$kernel->boot();
$doctrine = $kernel->getContainer()->get('doctrine');

return $doctrine->getManager();
