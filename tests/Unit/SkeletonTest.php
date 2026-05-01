<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SkeletonTest extends TestCase
{
    public function testProjectSkeletonExists(): void
    {
        $basePath = dirname(__DIR__, 2);

        self::assertFileExists($basePath . '/bootstrap.php');
        self::assertFileExists($basePath . '/public/index.php');
        self::assertFileExists($basePath . '/app/routes.php');
        self::assertDirectoryExists($basePath . '/app/Mcp/Tools');
        self::assertDirectoryDoesNotExist($basePath . '/app/Plugins');
    }
}
