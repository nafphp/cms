<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Analysis needs symbols and constants, without booting runtime services in
// every parallel worker or reading the developer's environment.
define('BASE_PATH', dirname(__DIR__));
define('PHPUNIT_RUNNING', true);
