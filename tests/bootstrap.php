<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}
