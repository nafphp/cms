<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use function Naf\app;
use function Naf\config;

final class ViewLocator
{
    public function __construct(private readonly ?string $basePath = null)
    {
    }

    public function find(string $view): ?string
    {
        $configured = config('view:paths', ['views', 'app/views', 'src/views']);
        $configured = is_array($configured) ? $configured : [$configured];
        $basePath   = $this->basePath ?? app()->getBasePath();
        $paths      = [];

        foreach ($configured as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $path     = trim($path);
            $absolute = str_starts_with($path, '/') || str_starts_with($path, '\\\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
            if ($absolute) {
                $paths[] = $path;
            } elseif ($basePath !== null) {
                $paths[] = rtrim($basePath, '/\\') . '/' . $path;
            }
        }

        foreach ([...$paths, ...app()->collectPluginResources('viewPaths')] as $path) {
            $file = rtrim($path, '/\\') . '/' . str_replace('.', '/', $view) . '.phtml';
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}
