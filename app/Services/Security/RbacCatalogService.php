<?php

declare(strict_types=1);

namespace Cms\Services\Security;

use Cms\Enums\Permission;
use Cms\Enums\Role;

final class RbacCatalogService
{
    public function roles(): array
    {
        return [
            [
                'id' => 1,
                'name' => Role::ADMIN->value,
                'permission_names' => array_map(static fn(Permission $permission) => $permission->value, Permission::cases()),
            ],
            [
                'id' => 2,
                'name' => Role::EDITOR->value,
                'permission_names' => [
                    Permission::ADMIN_ACCESS->value,
                    Permission::ARTICLES_VIEW->value,
                    Permission::ARTICLES_CREATE->value,
                    Permission::ARTICLES_EDIT->value,
                    Permission::ARTICLES_REVIEW->value,
                    Permission::ARTICLES_PUBLISH->value,
                    Permission::MEDIA_VIEW->value,
                    Permission::MEDIA_UPLOAD->value,
                ],
            ],
            [
                'id' => 3,
                'name' => Role::AUTHOR->value,
                'permission_names' => [
                    Permission::ADMIN_ACCESS->value,
                    Permission::ARTICLES_VIEW->value,
                    Permission::ARTICLES_CREATE->value,
                    Permission::ARTICLES_EDIT->value,
                    Permission::MEDIA_VIEW->value,
                    Permission::MEDIA_UPLOAD->value,
                ],
            ],
        ];
    }

    public function permissions(): array
    {
        $roleCounts = [];
        foreach ($this->roles() as $role) {
            foreach ($role['permission_names'] as $permission) {
                $roleCounts[$permission] = ($roleCounts[$permission] ?? 0) + 1;
            }
        }

        return array_map(
            static fn(Permission $permission, int $index): array => [
                'id' => $index + 1,
                'name' => $permission->value,
                'role_count' => $roleCounts[$permission->value] ?? 0,
            ],
            Permission::cases(),
            array_keys(Permission::cases())
        );
    }
}
