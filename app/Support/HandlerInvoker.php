<?php

declare(strict_types=1);

namespace NixCMS\Support;

use function NixPHP\app;

final class HandlerInvoker
{
    private function __construct()
    {
    }

    /**
     * @param callable|array{0: class-string, 1: string}|class-string $handler
     * @param array<int, mixed> $params
     */
    public static function call(callable|array|string $handler, array $params = []): mixed
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $instance = self::make($handler[0]);
            return $instance->{$handler[1]}(...$params);
        }

        if (is_string($handler) && class_exists($handler)) {
            return self::make($handler)(...$params);
        }

        return $handler(...$params);
    }

    /**
     * @param class-string $class
     */
    private static function make(string $class): object
    {
        try {
            $container = app()->container();
            if (method_exists($container, 'make')) {
                return $container->make($class);
            }
        } catch (\Throwable) {
            // Isolated unit tests may invoke handlers before the NixPHP app container is booted.
        }

        return new $class();
    }
}
