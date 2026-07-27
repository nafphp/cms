<?php

declare(strict_types=1);

use function NixCMS\cms;
use function NixPHP\app;

$packageRoot = dirname(__DIR__);

$missing = array_values(array_filter([
    'nixphp/cli',
    'nixphp/database',
    'nixphp/session',
    'nixphp/form',
    'nixphp/view',
    'nixphp/i18n',
    'nixphp/orm',
    'nixphp/queue',
    'nixphp/schedule',
    'nixphp/mail',
    'nixphp/mcp',
], static fn(string $plugin): bool => !app()->hasPlugin($plugin)));

if ($missing !== []) {
    throw new RuntimeException(sprintf(
        'NixCMS must be loaded after its NixPHP dependencies. Put fkde/nixcms last in app/plugins.php; not loaded yet: %s.',
        implode(', ', $missing),
    ));
}

// NixPHP loads plugin routes before its bootstrap. These registries must exist
// before the page and API definitions below are constructed.
cms()->contentTypes()->addMany(require $packageRoot . '/app/content.php');
cms()->settings()->addMany(require $packageRoot . '/app/settings.php');
cms()->navigation()->addMany(require $packageRoot . '/app/navigation.php');

cms()->pages()->addMany(require __DIR__ . '/pages.php');
cms()->api()->addMany(require __DIR__ . '/api.php');

$cliCommand = PHP_SAPI === 'cli' ? (string) ($_SERVER['argv'][1] ?? '') : '';
$shouldRegisterHttpRoutes = PHP_SAPI !== 'cli' || $cliCommand === 'route:debug';

if ($shouldRegisterHttpRoutes) {
    cms()->pages()->registerRoutes();
    cms()->api()->registerRoutes();
}