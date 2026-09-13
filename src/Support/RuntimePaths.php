<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use function Naf\app;
use function Naf\config;

final class RuntimePaths
{
    /**
     * Resource roots, probed in order. `src/Resources` is the current layout;
     * `app/Resources` is kept so an older host or package still resolves.
     *
     * @var list<string>
     */
    private const array RESOURCE_ROOTS = ['src/Resources', 'app/Resources'];

    /**
     * View roots, probed in order. Mirrors the convention the framework uses
     * when it registers a plugin's view path.
     *
     * @var list<string>
     */
    private const array VIEW_ROOTS = ['src/views', 'views', 'app/views'];

    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Resolve a resource shipped inside this package
     *
     * @param string $relative Path below the resource root, e.g. 'lang'
     *
     * @return string The first existing candidate, or the preferred location
     *                when none exists yet
     */
    public static function packageResource(string $relative): string
    {
        return self::resource(self::packageRoot(), $relative);
    }

    /**
     * Resolve a resource the host may override
     *
     * @param string $relative Path below the resource root, e.g. 'pages/page_templates.php'
     *
     * @return string The first existing candidate, or the preferred location
     *                when none exists yet
     */
    public static function hostResource(string $relative): string
    {
        return self::resource(self::hostRoot(), $relative);
    }

    /**
     * Resolve a resource below an explicit root
     *
     * @param string $root     Absolute root directory
     * @param string $relative Path below the resource root
     *
     * @return string The first existing candidate, or the preferred location
     *                when none exists yet
     */
    public static function resource(string $root, string $relative): string
    {
        return self::firstExisting($root, self::RESOURCE_ROOTS, $relative);
    }

    /**
     * Resolve the view directory below an explicit root
     *
     * @param string $root     Absolute root directory
     * @param string $relative Path below the view root, e.g. 'page/blocks'
     *
     * @return string The first existing candidate, or the preferred location
     *                when none exists yet
     */
    public static function views(string $root, string $relative = ''): string
    {
        return self::firstExisting($root, self::VIEW_ROOTS, $relative);
    }

    /**
     * @param list<string> $roots
     */
    private static function firstExisting(string $root, array $roots, string $relative): string
    {
        $root = rtrim($root, '/\\');
        $relative = trim($relative, '/\\');
        $suffix = $relative === '' ? '' : '/' . $relative;

        // Prefer the root that already holds the requested path.
        foreach ($roots as $candidate) {
            $path = $root . '/' . $candidate . $suffix;

            if (file_exists($path)) {
                return $path;
            }
        }

        // Otherwise use the first root that exists at all, so that a path for
        // something not there yet still points at the layout actually in use.
        foreach ($roots as $candidate) {
            if (is_dir($root . '/' . $candidate)) {
                return $root . '/' . $candidate . $suffix;
            }
        }

        return $root . '/' . $roots[0] . $suffix;
    }

    public static function hostRoot(): string
    {
        $root = app()->getBasePath();

        return is_string($root) && $root !== '' ? rtrim($root, '/\\') : getcwd();
    }

    public static function storageRoot(): string
    {
        return self::hostPath((string) config('cms:runtime_path', 'storage/nafcms'));
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
        return self::packageResource('prompts/local-ai');
    }

    public static function packageTranslations(): string
    {
        return self::packageResource('lang');
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
