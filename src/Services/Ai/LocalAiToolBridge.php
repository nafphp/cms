<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Ai;

use Naf\CMS\Enums\Permission;
use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Services\Ai\LocalAiToolCatalog;
use Naf\CMS\Services\Ai\ToolRouting\SemanticToolRouter;
use Naf\CMS\Services\Ai\ToolRouting\ToolContext;
use Naf\CMS\Services\Ai\ToolRouting\ToolRouterInterface;
use Naf\MCP\Auth\McpIdentity;
use Naf\MCP\Tools\ScopedToolInterface;
use function Naf\MCP\tool;
use function Naf\CMS\user;

final class LocalAiToolBridge
{
    public function __construct(
        private ?ToolRouterInterface $router = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $registry = tool();
        $identity = $this->identity();
        $definitions = array_map(static function (array $definition) use ($registry): array {
            $tool = $registry->getTool((string) ($definition['name'] ?? ''));
            $metadata = $tool instanceof LocalAiToolMetadataInterface ? $tool->localAiMetadata() : [];
            if ($tool instanceof ScopedToolInterface) {
                $metadata['scopes'] = $tool->requiredScopes();
            }

            return [
                ...$definition,
                'meta' => [
                    ...$metadata,
                    ...(is_array($definition['meta'] ?? null) ? $definition['meta'] : []),
                ],
            ];
        }, $registry->definitions($identity));

        return (new LocalAiToolCatalog())->enrich($definitions);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function selectDefinitions(string $message, array $context = [], int $limit = 12, bool $includeSearchTool = true): array
    {
        $definitions = $this->definitions();
        if (!$includeSearchTool) {
            $definitions = array_values(array_filter(
                $definitions,
                static fn(array $definition): bool => (string) ($definition['name'] ?? '') !== 'cms_tools_search',
            ));
        }

        $router = $this->router ?? new SemanticToolRouter($definitions);

        return $router->select($message, ToolContext::fromArray($context), $limit);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, array $arguments = []): mixed
    {
        $name = trim($name);
        return tool()->call($name, $arguments, $this->identity());
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
