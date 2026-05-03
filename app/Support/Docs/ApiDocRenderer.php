<?php

declare(strict_types=1);

namespace NixCMS\Support\Docs;

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;

final class ApiDocRenderer implements DocBlockRenderer
{
    public function name(): string
    {
        return 'api';
    }

    public function render(): string
    {
        $endpoints = cms()->api()->all();
        uasort($endpoints, static fn(ApiEndpoint $a, ApiEndpoint $b): int => [$a->path(), $a->method(), $a->id()] <=> [$b->path(), $b->method(), $b->id()]);

        return MarkdownTable::render(
            ['ID', 'Method', 'Path', 'Permissions'],
            array_map(
                fn(ApiEndpoint $endpoint): array => [
                    '`' . $endpoint->id() . '`',
                    '`' . $endpoint->method() . '`',
                    '`' . $endpoint->path() . '`',
                    $this->permissions($endpoint),
                ],
                array_values($endpoints)
            )
        );
    }

    private function permissions(ApiEndpoint $endpoint): string
    {
        $permissions = array_map(
            static fn(Permission $permission): string => '`' . $permission->value . '`',
            $endpoint->requiredPermissions()
        );

        if ($permissions === []) {
            return '';
        }

        $prefix = $endpoint->requiresAnyPermission() ? 'any of ' : '';
        return $prefix . implode(', ', $permissions);
    }
}
