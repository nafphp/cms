<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SkeletonTest extends TestCase
{
    public function testPluginExposesRegistryEntryPoints(): void
    {
        $basePath = dirname(__DIR__, 2);

        self::assertFileExists($basePath . '/bootstrap.php');
        self::assertFileExists($basePath . '/src/routes.php');
        self::assertFileExists($basePath . '/src/pages.php');
        self::assertFileExists($basePath . '/src/api.php');
        self::assertFileExists($basePath . '/src/content.php');
        self::assertDirectoryExists($basePath . '/src/Resources/pages');
        self::assertDirectoryExists($basePath . '/src/Resources/api');
        self::assertDirectoryExists($basePath . '/src/Controllers/Api');
        self::assertDirectoryExists($basePath . '/src/Controllers/Page');
        self::assertDirectoryExists($basePath . '/src/Content/Article');
        self::assertDirectoryExists($basePath . '/src/Mcp/Tools');
    }

    /**
     * This package is a plugin, not an application.
     *
     * The host owns the front controller, the webroot and the lifecycle. A
     * front controller here would invite running the package standalone, which
     * boots without a host config and fails in confusing ways. `dev/` may exist
     * locally as a throwaway harness, but it is gitignored and must never be
     * required for the package to be valid.
     */
    public function testPluginShipsNoFrontController(): void
    {
        $basePath = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($basePath . '/public/index.php');
        self::assertFileDoesNotExist($basePath . '/public/router.php');
    }
}
