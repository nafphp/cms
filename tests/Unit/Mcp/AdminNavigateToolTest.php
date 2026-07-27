<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use src\Mcp\Tools\AdminNavigateTool;
use PHPUnit\Framework\TestCase;

final class AdminNavigateToolTest extends TestCase
{
    public function testNavigatesToPageBuilderCreateScreen(): void
    {
        $result = (new AdminNavigateTool())->handle([
            'target' => 'pageBuilder',
        ]);

        self::assertTrue($result['success']);
        self::assertSame('/admin/pages/create', $result['url']);
        self::assertSame('admin.navigate', $result['uiActions'][0]['action']);
        self::assertSame('/admin/pages/create', $result['uiActions'][0]['payload']['url']);
    }

    public function testNavigatesToExistingPageBuilderScreen(): void
    {
        $result = (new AdminNavigateTool())->handle([
            'target' => 'pageBuilder',
            'id' => 12,
        ]);

        self::assertSame('/admin/pages/create?id=12', $result['url']);
    }
}
