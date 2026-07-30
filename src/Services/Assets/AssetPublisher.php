<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Assets;

use Composer\InstalledVersions;
use NixPHP\CMS\Support\AtomicFileWriter;
use NixPHP\CMS\Support\RuntimePaths;

final class AssetPublisher
{
    private const array DIRECTORIES = ['css', 'fonts', 'images', 'js'];
    private const string MANIFEST = '.nixcms-assets.json';

    public function __construct(
        private readonly ?string $sourceRoot = null,
        private readonly ?string $publicRoot = null,
        private readonly ?AtomicFileWriter $writer = null,
    ) {
    }

    /** @return array{current: bool, missing: list<string>, outdated: list<string>, conflicts: list<string>, stale: list<string>} */
    public function status(): array
    {
        $source = $this->sourceFiles();
        $manifest = $this->manifestFiles();
        $missing = [];
        $outdated = [];
        $conflicts = [];

        foreach ($source as $relative => $hash) {
            $target = $this->target($relative);
            if (!is_file($target)) {
                $missing[] = $relative;
                continue;
            }

            $actual = hash_file('sha256', $target);
            if ($actual === $hash) {
                continue;
            }

            if (isset($manifest[$relative]) && hash_equals($manifest[$relative], (string) $actual)) {
                $outdated[] = $relative;
            } else {
                $conflicts[] = $relative;
            }
        }

        $stale = array_values(array_diff(array_keys($manifest), array_keys($source)));
        sort($missing);
        sort($outdated);
        sort($conflicts);
        sort($stale);

        return [
            'current' => $missing === [] && $outdated === [] && $conflicts === [] && $stale === [],
            'missing' => $missing,
            'outdated' => $outdated,
            'conflicts' => $conflicts,
            'stale' => $stale,
        ];
    }

    /** @return array{published: int, removed: int, version: string} */
    public function publish(): array
    {
        if (realpath($this->source()) === realpath($this->destination())) {
            return ['published' => 0, 'removed' => 0, 'version' => $this->version()];
        }

        $status = $this->status();
        if ($status['conflicts'] !== []) {
            throw new \RuntimeException('Asset publish conflicts: ' . implode(', ', $status['conflicts']));
        }

        $source = $this->sourceFiles();
        $oldManifest = $this->manifestFiles();
        $published = 0;
        foreach ($source as $relative => $hash) {
            $target = $this->target($relative);
            if (is_file($target) && hash_file('sha256', $target) === $hash) {
                continue;
            }

            $contents = file_get_contents($this->source() . '/' . $relative);
            if (!is_string($contents)) {
                throw new \RuntimeException(sprintf('Could not read package asset "%s".', $relative));
            }

            ($this->writer ?? new AtomicFileWriter())->write($target, $contents, 0644);
            $published++;
        }

        $removed = 0;
        foreach (array_diff(array_keys($oldManifest), array_keys($source)) as $relative) {
            $target = $this->target($relative);
            if (is_file($target) && hash_file('sha256', $target) === $oldManifest[$relative]) {
                unlink($target);
                $removed++;
            }
        }

        $manifest = json_encode([
            'package' => 'nixphp/cms',
            'version' => $this->version(),
            'files' => $source,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        ($this->writer ?? new AtomicFileWriter())->write($this->manifestPath(), $manifest, 0644);

        return ['published' => $published, 'removed' => $removed, 'version' => $this->version()];
    }

    public function remove(): int
    {
        $manifest = $this->manifestFiles();
        $conflicts = [];
        foreach ($manifest as $relative => $hash) {
            $target = $this->target($relative);
            if (is_file($target) && hash_file('sha256', $target) !== $hash) {
                $conflicts[] = $relative;
            }
        }

        if ($conflicts !== []) {
            sort($conflicts);
            throw new \RuntimeException('Asset removal conflicts: ' . implode(', ', $conflicts));
        }

        $removed = 0;
        foreach (array_keys($manifest) as $relative) {
            $target = $this->target($relative);
            if (is_file($target)) {
                unlink($target);
                $removed++;
            }
        }

        if (is_file($this->manifestPath())) {
            unlink($this->manifestPath());
        }

        return $removed;
    }

    /** @return array<string, string> */
    private function sourceFiles(): array
    {
        $files = [];
        foreach (self::DIRECTORIES as $directory) {
            $root = $this->source() . '/' . $directory;
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->source()) + 1));
                if (
                    str_starts_with(basename($relative), '.')
                    || str_starts_with($relative, 'css/themes/')
                    || str_starts_with($relative, 'js/themes/')
                ) {
                    continue;
                }
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
        }

        ksort($files);
        return $files;
    }

    /** @return array<string, string> */
    private function manifestFiles(): array
    {
        if (!is_file($this->manifestPath())) {
            return [];
        }

        try {
            $manifest = json_decode((string) file_get_contents($this->manifestPath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
    }

    private function source(): string
    {
        return rtrim($this->sourceRoot ?? RuntimePaths::packageRoot() . '/public', '/\\');
    }

    private function destination(): string
    {
        return rtrim($this->publicRoot ?? RuntimePaths::publicRoot(), '/\\');
    }

    private function target(string $relative): string
    {
        return $this->destination() . '/' . $relative;
    }

    private function manifestPath(): string
    {
        return $this->destination() . '/' . self::MANIFEST;
    }

    private function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('nixphp/cms') ?? 'dev';
        } catch (\OutOfBoundsException) {
            return 'dev';
        }
    }
}
