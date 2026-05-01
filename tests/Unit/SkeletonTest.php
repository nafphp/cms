<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SkeletonTest extends TestCase
{
    public function testProjectSkeletonExposesRegistryEntryPoints(): void
    {
        $basePath = dirname(__DIR__, 2);

        self::assertFileExists($basePath . '/bootstrap.php');
        self::assertFileExists($basePath . '/public/index.php');
        self::assertFileExists($basePath . '/app/routes.php');
        self::assertFileExists($basePath . '/app/pages.php');
        self::assertFileExists($basePath . '/app/api.php');
        self::assertFileExists($basePath . '/app/content.php');
        self::assertDirectoryExists($basePath . '/app/Resources/pages');
        self::assertDirectoryExists($basePath . '/app/Resources/api');
        self::assertDirectoryExists($basePath . '/app/Controllers/Api');
        self::assertDirectoryExists($basePath . '/app/Controllers/Page');
        self::assertDirectoryExists($basePath . '/app/Content/Article');
        self::assertDirectoryExists($basePath . '/app/Mcp/Tools');
    }
}
