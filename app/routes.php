<?php

declare(strict_types=1);

cms()->pages()->addMany(require __DIR__ . '/pages.php');
cms()->api()->addMany(require __DIR__ . '/api.php');

$cliCommand = PHP_SAPI === 'cli' ? (string) ($_SERVER['argv'][1] ?? '') : '';
$shouldRegisterHttpRoutes = PHP_SAPI !== 'cli' || $cliCommand === 'route:debug';

if ($shouldRegisterHttpRoutes) {
    cms()->pages()->registerRoutes();
    cms()->api()->registerRoutes();
}
