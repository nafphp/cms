<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixPHP\CMS\Contracts\LocalAiToolMetadataInterface;
use NixPHP\CMS\Services\Ai\LocalAiToolBridge;
use NixPHP\MCP\Support\ToolRegistry;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function NixPHP\app;

final class LocalAiToolBridgeTest extends TestCase
{
    private ToolRegistry $originalRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRegistry = app()->container()->get(ToolRegistry::class);

        $registry = new ToolRegistry();
        $registry->register(new LocalAiUnscopedToolFixture());
        $registry->register(new LocalAiScopedToolFixture());

        app()->container()->set(ToolRegistry::class, $registry);
        app()->container()->set(LoggerInterface::class, new NullLogger());
    }

    protected function tearDown(): void
    {
        app()->container()->set(ToolRegistry::class, $this->originalRegistry);
        parent::tearDown();
    }

    public function testDefinitionsUseLocalIdentityScopesAndHideScopedToolsWithoutUserPermissions(): void
    {
        $definitions = (new LocalAiToolBridge())->definitions();

        self::assertSame(['local_ai_unscoped_fixture'], array_column($definitions, 'name'));
        self::assertSame('Fixture metadata title', $definitions[0]['meta']['title']);
        self::assertSame(['fixtures.read'], $definitions[0]['meta']['capabilities']);
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

    public function testWriteToolKeepsOwnershipOfItsTransactionBoundary(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new LocalAiTransactionProbeWriteToolFixture());

        app()->container()->set(ToolRegistry::class, $registry);

        $result = (new LocalAiToolBridge())->call('local_ai_write_fixture');

        self::assertSame(['success' => true], $result);
    }
}

final class LocalAiUnscopedToolFixture implements ToolInterface, LocalAiToolMetadataInterface
{
    public function localAiMetadata(): array
    {
        return [
            'title' => 'Fixture metadata title',
            'capabilities' => ['fixtures.read'],
            'mode' => 'read',
        ];
    }

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

final class LocalAiTransactionProbeWriteToolFixture implements ToolInterface
{
    public function name(): string
    {
        return 'local_ai_write_fixture';
    }

    public function description(): string
    {
        return 'Creates a fixture through its own domain transaction.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];
    }

    public function handle(array $args): array
    {
        return ['success' => true];
    }
}
