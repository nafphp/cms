<?php

declare(strict_types=1);

namespace NixCMS\Support\Docs;

use NixCMS\Enums\Permission;

final class PermissionsDocRenderer implements DocBlockRenderer
{
    public function name(): string
    {
        return 'permissions';
    }

    public function render(): string
    {
        return MarkdownTable::render(
            ['Permission', 'Description'],
            array_map(
                fn(Permission $permission): array => [
                    '`' . $permission->value . '`',
                    $this->describe($permission),
                ],
                Permission::cases()
            )
        );
    }

    private function describe(Permission $permission): string
    {
        return match ($permission) {
            Permission::ADMIN_ACCESS => 'Access the admin area.',
            Permission::SYSTEM_READ => 'Read system status and health information.',
            Permission::MCP_TOOLS_CALL => 'Call allowed MCP tools.',
            default => $this->humanize($permission->value),
        };
    }

    private function humanize(string $value): string
    {
        $parts = explode('.', $value);
        $action = array_pop($parts) ?: 'use';
        $subject = implode(' ', $parts);

        return ucfirst(str_replace('_', ' ', $action)) . ' ' . str_replace('_', ' ', $subject) . '.';
    }
}
