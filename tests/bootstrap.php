<?php

declare(strict_types=1);

use NixPHP\Database\Core\Database;
use function NixPHP\app;
use function NixPHP\guard;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

require_once dirname(__DIR__) . '/vendor/nixphp/view/src/view_helpers.php';

/*
 * Unit tests must never reach a developer's database.
 *
 * The suite boots the real application, which reads .env and resolves the
 * database config, so repositories would silently query whatever MySQL happens
 * to run on the machine. Results then depend on local data instead of the code
 * under test. Tests that need storage set up their own connection through
 * DatabaseTestCase.
 */
app()->container()->set(Database::class, static fn(): ?Database => null);

guard()->register('safeOutput', static function (string|array|null $value): string|array|null {
    if (is_array($value)) {
        return array_map(
            static fn(mixed $item): mixed => is_string($item) ? htmlspecialchars($item, ENT_QUOTES, 'UTF-8') : $item,
            $value,
        );
    }

    return $value === null ? null : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
});
