<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixCMS\Services\Ai\LocalAiToolBridge;
use NixPHP\MCP\Support\ToolRegistry;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PHPUnit\Framework\TestCase;
use function NixPHP\app;

final class LocalAiToolBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $registry = new ToolRegistry();
        $registry->register(new LocalAiUnscopedToolFixture());
        $registry->register(new LocalAiScopedToolFixture());

        app()->container()->set(ToolRegistry::class, $registry);
    }

    public function testDefinitionsUseLocalIdentityScopesAndHideScopedToolsWithoutUserPermissions(): void
    {
        $definitions = (new LocalAiToolBridge())->definitions();

        self::assertSame(['local_ai_unscoped_fixture'], array_column($definitions, 'name'));
    }

    public function testCallTrimsToolNameAndPassesArguments(): void
    {
        $result = (new LocalAiToolBridge())->call(' local_ai_unscoped_fixture ', ['topic' => 'coverage']);

        self::assertSame([
            'success' => true,
            'topic' => 'coverage',
        ], $result);
    }

    public function testCallRejectsScopedToolWhenLocalUserHasNoMatchingScope(): void
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Not allowed to call tool: local_ai_scoped_fixture');

        (new LocalAiToolBridge())->call('local_ai_scoped_fixture');
    }
}

final class LocalAiUnscopedToolFixture implements ToolInterface
{
    public function name(): string
    {
        return 'local_ai_unscoped_fixture';
    }

    public function description(): string
    {
        return 'Unscoped fixture.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function handle(array $args): array
    {
        return [
            'success' => true,
            'topic' => (string) ($args['topic'] ?? ''),
        ];
    }
}

final class LocalAiScopedToolFixture implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'local_ai_scoped_fixture';
    }

    public function description(): string
    {
        return 'Scoped fixture.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function handle(array $args): array
    {
        return ['success' => true];
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }
}
