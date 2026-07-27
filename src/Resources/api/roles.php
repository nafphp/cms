<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Rbac\CreatePermission;
use src\Handlers\Api\Rbac\CreateRole;
use src\Handlers\Api\Rbac\DeletePermission;
use src\Handlers\Api\Rbac\DeleteRole;
use src\Handlers\Api\Rbac\ListPermissions;
use src\Handlers\Api\Rbac\ListRoles;
use src\Handlers\Api\Rbac\UpdatePermission;
use src\Handlers\Api\Rbac\UpdateRole;
use src\Handlers\Api\Rbac\UpdateRolePermissions;

return [
    ApiEndpoint::get('api.admin.rbac.roles', '/api/admin/rbac/roles')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(ListRoles::class),

    ApiEndpoint::post('api.admin.rbac.roles.create', '/api/admin/rbac/roles/create')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(CreateRole::class),

    ApiEndpoint::post('api.admin.rbac.roles.update', '/api/admin/rbac/roles/update')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(UpdateRole::class),

    ApiEndpoint::post('api.admin.rbac.roles.delete', '/api/admin/rbac/roles/delete')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(DeleteRole::class),

    ApiEndpoint::post('api.admin.rbac.roles.permissions', '/api/admin/rbac/roles/permissions')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->requirePermission(Permission::PERMISSIONS_MANAGE)
        ->setHandler(UpdateRolePermissions::class),

    ApiEndpoint::get('api.admin.rbac.permissions', '/api/admin/rbac/permissions')
        ->requirePermission(Permission::PERMISSIONS_MANAGE)
        ->setHandler(ListPermissions::class),

    ApiEndpoint::post('api.admin.rbac.permissions.create', '/api/admin/rbac/permissions/create')
        ->requirePermission(Permission::PERMISSIONS_MANAGE)
        ->setHandler(CreatePermission::class),

    ApiEndpoint::post('api.admin.rbac.permissions.update', '/api/admin/rbac/permissions/update')
        ->requirePermission(Permission::PERMISSIONS_MANAGE)
        ->setHandler(UpdatePermission::class),

    ApiEndpoint::post('api.admin.rbac.permissions.delete', '/api/admin/rbac/permissions/delete')
        ->requirePermission(Permission::PERMISSIONS_MANAGE)
        ->setHandler(DeletePermission::class),
];
