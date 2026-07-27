<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
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
            return $instance->{$handler[1]}(...self::parametersFor(new ReflectionMethod($instance, $handler[1]), $params));
        }

        if (is_array($handler) && isset($handler[0], $handler[1]) && is_object($handler[0])) {
            return $handler[0]->{$handler[1]}(...self::parametersFor(new ReflectionMethod($handler[0], $handler[1]), $params));
        }

        if (is_string($handler) && class_exists($handler)) {
            $instance = self::make($handler);
            return $instance(...self::parametersFor(new ReflectionMethod($instance, '__invoke'), $params));
        }

        if (is_object($handler) && method_exists($handler, '__invoke')) {
            return $handler(...self::parametersFor(new ReflectionMethod($handler, '__invoke'), $params));
        }

        return $handler(...self::parametersFor(new ReflectionFunction($handler), $params));
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    private static function parametersFor(ReflectionFunction|ReflectionMethod $reflection, array $params): array
    {
        if (array_is_list($params) || self::hasArrayVariadicParameter($reflection)) {
            return $params;
        }

        $accepted = [];
        foreach ($reflection->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $params)) {
                $accepted[$parameter->getName()] = $params[$parameter->getName()];
            }
        }

        return $accepted;
    }

    private static function hasArrayVariadicParameter(ReflectionFunction|ReflectionMethod $reflection): bool
    {
        foreach ($reflection->getParameters() as $parameter) {
            if (!$parameter->isVariadic()) {
                continue;
            }

            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->getName() === 'array') {
                return true;
            }
        }

        return false;
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
