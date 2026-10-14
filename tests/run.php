<?php

declare(strict_types=1);

putenv('APP_ENV=test');
$_ENV['APP_ENV']    = 'test';
$_SERVER['APP_ENV'] = 'test';

$phpunit   = dirname(__DIR__) . '/vendor/bin/phpunit';
$arguments = array_slice($_SERVER['argv'] ?? [], 1);
if ($arguments === []) {
    $arguments = ['tests'];
}

$command = implode(' ', [
    escapeshellarg(PHP_BINARY),
    escapeshellarg($phpunit),
    ...array_map('escapeshellarg', $arguments),
]);

passthru($command, $exitCode);
exit($exitCode);
