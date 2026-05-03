<?php

declare(strict_types=1);

use NixCMS\Content\Page\Block\BlockTypeInterface;

$directory = __DIR__ . '/Content/Page/Block/Types';
$namespace = 'NixCMS\\Content\\Page\\Block\\Types\\';
$types = [];

foreach (glob($directory . '/*.php') ?: [] as $file) {
    $class = $namespace . basename($file, '.php');
    if (!class_exists($class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract() || !$reflection->implementsInterface(BlockTypeInterface::class)) {
        continue;
    }

    $types[] = $reflection->newInstance();
}

usort(
    $types,
    static fn(BlockTypeInterface $a, BlockTypeInterface $b): int => strnatcasecmp($a->id(), $b->id()),
);

return $types;
