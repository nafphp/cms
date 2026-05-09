<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixCMS\Services\Ai\LocalAiToolBridge;
use NixPHP\Database\Core\Database;
use NixPHP\MCP\Support\ToolRegistry;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        app()->container()->set(LoggerInterface::class, new NullLogger());
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

    public function testWriteToolCallRollsBackWhenToolThrowsAfterWriting(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE ai_tool_transaction_fixture (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)');

        $registry = new ToolRegistry();
        $registry->register(new LocalAiThrowingWriteToolFixture());

        app()->container()->set(ToolRegistry::class, $registry);
        app()->container()->set(Database::class, new Database(['driver' => 'sqlite', 'database' => ':memory:'], fn(): PDO => $pdo));

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('write failed after insert');

        try {
            (new LocalAiToolBridge())->call('local_ai_write_fixture', ['title' => 'Atomic']);
        } finally {
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM ai_tool_transaction_fixture')->fetchColumn());
        }
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

final class LocalAiThrowingWriteToolFixture implements ToolInterface
{
    public function name(): string
    {
        return 'local_ai_write_fixture';
    }

    public function description(): string
    {
        return 'Creates a fixture row and then throws to verify write rollback.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];
    }

    public function handle(array $args): array
    {
        $connection = \NixPHP\Database\database();
        $statement = $connection->prepare('INSERT INTO ai_tool_transaction_fixture (title) VALUES (:title)');
        $statement->execute(['title' => (string) ($args['title'] ?? '')]);

        throw new \RuntimeException('write failed after insert');
    }
}
