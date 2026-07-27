<?php

declare(strict_types=1);

namespace src\Support;

use function NixPHP\app;
use function NixPHP\config;

final class RuntimePaths
{
    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function hostRoot(): string
    {
        $root = app()->getBasePath();

        return is_string($root) && $root !== '' ? rtrim($root, '/\\') : getcwd();
    }

    public static function storageRoot(): string
    {
        return self::hostPath((string) config('cms:runtime_path', 'storage/nixcms'));
    }

    public static function prompts(): string
    {
        return self::storageRoot() . '/prompts/local-ai';
    }

    public static function translations(): string
    {
        return self::storageRoot() . '/translations';
    }

    public static function cache(string $name = ''): string
    {
        $path = self::storageRoot() . '/cache';
        return $name === '' ? $path : $path . '/' . trim($name, '/\\');
    }

    public static function packagePrompts(): string
    {
        return self::packageRoot() . '/app/Resources/prompts/local-ai';
    }

    public static function packageTranslations(): string
    {
        return self::packageRoot() . '/app/Resources/lang';
    }

    public static function publicRoot(): string
    {
        return self::hostPath((string) config('view:assets:publicPath', 'public'));
    }

    public static function hostPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('Runtime path cannot be empty.');
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return rtrim($path, '/\\');
        }

        $segments = preg_split('#[\\\\/]#', $path) ?: [];
        if (in_array('..', $segments, true)) {
            throw new \InvalidArgumentException('Relative runtime paths cannot leave the host root.');
        }

        return self::hostRoot() . '/' . trim($path, '/\\');
    }
}
