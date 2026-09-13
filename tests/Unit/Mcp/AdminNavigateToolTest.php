<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Naf\CMS\Mcp\Tools\AdminNavigateTool;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function targetProvider(): array
    {
        return [
            'users' => ['users', 0, '/admin/users'],
            'user create' => ['userEditor', 0, '/admin/users/create'],
            'user edit' => ['userEditor', 7, '/admin/users/7/edit'],
            'roles' => ['roles', 0, '/admin/roles'],
            'navigation' => ['navigation', 0, '/admin/navigation'],
            'navigation edit' => ['navigationEditor', 3, '/admin/navigation/3/edit'],
        ];
    }

    #[DataProvider('targetProvider')]
    public function testNavigatesToUserAndRoleScreens(string $target, int $id, string $expected): void
    {
        $result = (new AdminNavigateTool())->handle(array_filter([
            'target' => $target,
            'id' => $id,
        ]));

        self::assertTrue($result['success']);
        self::assertSame($expected, $result['url']);
    }

    public function testEveryDeclaredTargetResolvesToAUrl(): void
    {
        $tool = new AdminNavigateTool();
        $targets = $tool->inputSchema()['properties']['target']['enum'] ?? [];

        self::assertNotSame([], $targets);

        foreach ($targets as $target) {
            $result = $tool->handle(['target' => $target]);
            self::assertStringStartsWith('/admin', $result['url'], $target);
        }
    }
}
