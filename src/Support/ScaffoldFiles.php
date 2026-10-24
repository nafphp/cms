<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use RuntimeException;

final class ScaffoldFiles
{
    public static function studly(string $id): string
    {
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $id)));

        return preg_match('/^[0-9]/', $name) === 1 ? '_' . $name : $name;
    }

    public static function validateId(string $id): void
    {
        if (preg_match('/^[a-z0-9-]+$/D', $id) !== 1) {
            throw new RuntimeException('Identifier must contain only lowercase letters, digits and hyphens.');
        }
    }

    public static function hostNamespace(string $root): string
    {
        $file     = $root . '/composer.json';
        $composer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $prefixes = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : [];
        foreach (is_array($prefixes) ? $prefixes : [] as $prefix => $paths) {
            foreach ((array) $paths as $path) {
                if (!is_string($path)) {
                    continue;
                }
                $path = str_replace('\\', '/', $path);
                $path = str_starts_with($path, './') ? substr($path, 2) : $path;
                if (rtrim($path, '/') === 'src' && is_string($prefix)) {
                    return rtrim($prefix, '\\');
                }
            }
        }
        throw new RuntimeException('Host composer.json needs a PSR-4 prefix mapped to src/.');
    }

    /** @param array<string, string> $files */
    public static function write(array $files): void
    {
        foreach ($files as $file => $contents) {
            if (file_exists($file) || is_link($file)) {
                throw new RuntimeException('Target already exists: ' . $file);
            }
        }
        foreach ($files as $file => $contents) {
            $directory = dirname($file);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create directory: ' . $directory);
            }
            $handle = fopen($file, 'x');
            if ($handle === false) {
                throw new RuntimeException('Could not create target: ' . $file);
            }

            try {
                if (fwrite($handle, $contents) !== strlen($contents)) {
                    throw new RuntimeException('Could not write target: ' . $file);
                }
            } finally {
                fclose($handle);
            }
        }
    }
}
