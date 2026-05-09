<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai;

use NixCMS\Enums\Permission;
use NixCMS\Services\Ai\ToolRouting\SemanticToolRouter;
use NixCMS\Services\Ai\ToolRouting\ToolContext;
use NixCMS\Services\Ai\ToolRouting\ToolRouterInterface;
use NixPHP\MCP\Auth\McpIdentity;
use Throwable;
use function NixPHP\Database\database;
use function NixPHP\log;
use function NixPHP\MCP\tool;
use function NixCMS\user;

final class LocalAiToolBridge
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cachedDefinitions = null;
    private static ?object $cachedRegistry = null;

    public function __construct(
        private ?ToolRouterInterface $router = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $registry = tool();
        if (self::$cachedDefinitions !== null && self::$cachedRegistry === $registry) {
            return self::$cachedDefinitions;
        }

        self::$cachedRegistry = $registry;
        self::$cachedDefinitions = (new LocalAiToolCatalog())->enrich($registry->definitions($this->identity()));

        return self::$cachedDefinitions;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function selectDefinitions(string $message, array $context = [], int $limit = 12): array
    {
        $definitions = $this->definitions();
        $router = $this->router ?? new SemanticToolRouter($definitions);

        return $router->select($message, ToolContext::fromArray($context), $limit);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, array $arguments = []): mixed
    {
        $name = trim($name);
        $call = fn(): mixed => tool()->call($name, $arguments, $this->identity());
        if (!$this->requiresTransaction($name)) {
            return $call();
        }

        $connection = database();
        if (!$connection instanceof \PDO || $connection->inTransaction()) {
            return $call();
        }

        $connection->beginTransaction();
        try {
            $result = $call();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            log()->error('LocalAiToolBridge.writeTool failed', [
                'exception' => $exception,
                'tool' => $name,
            ]);

            throw $exception;
        }
    }

    private function requiresTransaction(string $name): bool
    {
        foreach ($this->definitions() as $definition) {
            if ((string) ($definition['name'] ?? '') !== $name) {
                continue;
            }

            $meta = is_array($definition['meta'] ?? null) ? $definition['meta'] : [];

            return (string) ($meta['mode'] ?? 'read') === 'write'
                || (bool) ($meta['destructive'] ?? false) === true;
        }

        return false;
    }

    private function identity(): McpIdentity
    {
        $helper = user();
        $user = $helper?->get();
        $id = $user?->getId();
        $name = $user?->getDisplayName() ?: $user?->getUsername() ?: 'Local AI admin';

        return new McpIdentity(
            id: $id !== null ? 'admin-user-' . $id : 'admin-session',
            name: $name,
            scopes: $this->scopes(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function scopes(): array
    {
        $helper = user();
        if ($helper === null) {
            return [];
        }

        $scopes = [];
        $map = [
            Permission::ADMIN_ACCESS->value => ['admin:navigate'],
            Permission::MCP_TOOLS_CALL->value => ['tools:read'],
            Permission::SYSTEM_READ->value => ['system:read'],
            Permission::QUEUE_READ->value => ['queue:read'],
            Permission::ARTICLES_VIEW->value => ['articles:read'],
            Permission::ARTICLES_CREATE->value => ['articles:write'],
            Permission::ARTICLES_EDIT->value => ['articles:write'],
            Permission::PAGES_VIEW->value => ['pages:read'],
            Permission::PAGES_CREATE->value => ['pages:write'],
            Permission::PAGES_EDIT->value => ['pages:write'],
            Permission::THEMES_VIEW->value => ['themes:read'],
            Permission::THEMES_CREATE->value => ['themes:write'],
            Permission::THEMES_EDIT->value => ['themes:write'],
            Permission::FORMS_VIEW->value => ['content:read', 'forms:read'],
            Permission::FORMS_CREATE->value => ['content:write', 'forms:write'],
            Permission::FORMS_EDIT->value => ['content:write', 'forms:write'],
            Permission::MEDIA_VIEW->value => ['media:read'],
            Permission::USERS_VIEW->value => ['users:read'],
            Permission::ROLES_MANAGE->value => ['users:read'],
            Permission::PERMISSIONS_MANAGE->value => ['users:read'],
        ];

        foreach (Permission::cases() as $permission) {
            if ($helper->can($permission) !== true) {
                continue;
            }

            foreach ($map[$permission->value] ?? [] as $scope) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes));
    }
}
