<?php

declare(strict_types=1);

use function Naf\CMS\cms;
use function Naf\app;

$packageRoot = dirname(__DIR__);

// NAF loads plugin routes before its bootstrap. These registries must exist
// before the page and API definitions below are constructed.
cms()->contentTypes()->addMany(require $packageRoot . '/src/content.php');
cms()->settings()->addMany(require $packageRoot . '/src/settings.php');
cms()->navigation()->addMany(require $packageRoot . '/src/navigation.php');

cms()->pages()->addMany(require __DIR__ . '/pages.php');
cms()->api()->addMany(require __DIR__ . '/api.php');

// Registered unconditionally, CLI included. This used to be skipped outside
// HTTP except for one hard-coded command name, because registration built every
// stored page. It now only reads lightweight route descriptors, and returns
// nothing at all while the schema is missing, so commands that need routes
// (route:debug, sitemap generation, anything added later) simply have them.
cms()->pages()->registerRoutes();
cms()->api()->registerRoutes();
