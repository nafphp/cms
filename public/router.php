<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

if ($path !== '/') {
    $file = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
