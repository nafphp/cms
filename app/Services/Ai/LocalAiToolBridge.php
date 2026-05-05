<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai;

use NixCMS\Enums\Permission;
use NixPHP\MCP\Auth\McpIdentity;
use function NixPHP\MCP\tool;

final class LocalAiToolBridge
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return tool()->definitions($this->identity());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, array $arguments = []): mixed
    {
        return tool()->call(trim($name), $arguments, $this->identity());
    }

    private function identity(): McpIdentity
    {
        $helper = \user();
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
        $helper = \user();
        if ($helper === null) {
            return [];
        }

        $scopes = [];
        $map = [
            Permission::SYSTEM_READ->value => ['system:read'],
            Permission::QUEUE_READ->value => ['queue:read'],
            Permission::ARTICLES_VIEW->value => ['articles:read'],
            Permission::ARTICLES_CREATE->value => ['articles:write'],
            Permission::ARTICLES_EDIT->value => ['articles:write'],
            Permission::PAGES_VIEW->value => ['pages:read'],
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
