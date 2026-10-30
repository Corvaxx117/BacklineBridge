<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/migrations',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/deploy',
        __DIR__.'/tools',
    ])
    ->exclude('secrets')
    ->notPath('reference.php')
    ->append([__FILE__, __DIR__.'/bin/console', __DIR__.'/bin/phpunit']);

return new Config()
    ->setRiskyAllowed(false)
    ->setRules(['@Symfony' => true, 'single_line_throw' => false])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/cache/php-cs-fixer.cache');
