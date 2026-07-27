<?php

declare(strict_types=1);

use function NixPHP\CMS\cms;
use function NixPHP\app;

$packageRoot = dirname(__DIR__);

// NixPHP loads plugin routes before its bootstrap. These registries must exist
// before the page and API definitions below are constructed.
cms()->contentTypes()->addMany(require $packageRoot . '/src/content.php');
cms()->settings()->addMany(require $packageRoot . '/src/settings.php');
cms()->navigation()->addMany(require $packageRoot . '/src/navigation.php');

cms()->pages()->addMany(require __DIR__ . '/pages.php');
cms()->api()->addMany(require __DIR__ . '/api.php');

$cliCommand = PHP_SAPI === 'cli' ? (string) ($_SERVER['argv'][1] ?? '') : '';
$shouldRegisterHttpRoutes = PHP_SAPI !== 'cli' || $cliCommand === 'route:debug';

if ($shouldRegisterHttpRoutes) {
    cms()->pages()->registerRoutes();
    cms()->api()->registerRoutes();
}