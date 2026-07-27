<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\User\CreateInvite;
use src\Handlers\Api\User\DeleteUser;
use src\Handlers\Api\User\ListUsers;
use src\Handlers\Api\User\StoreUser;
use src\Handlers\Api\User\UpdateUser;
use src\Handlers\Api\User\UpdateUserPassword;
use src\Handlers\Api\User\UpdateUserRoles;

return [
    ApiEndpoint::get('api.admin.users.list', '/api/admin/users/list')
        ->requirePermission(Permission::USERS_VIEW)
        ->setHandler(ListUsers::class),

    ApiEndpoint::post('api.admin.users.store', '/api/admin/users/store')
        ->requirePermission(Permission::USERS_CREATE)
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(StoreUser::class),

    ApiEndpoint::post('api.admin.users.update', '/api/admin/users/update')
        ->requirePermission(Permission::USERS_EDIT)
        ->setHandler(UpdateUser::class),

    ApiEndpoint::post('api.admin.users.password', '/api/admin/users/password')
        ->requirePermission(Permission::USERS_EDIT)
        ->setHandler(UpdateUserPassword::class),

    ApiEndpoint::post('api.admin.users.roles', '/api/admin/users/roles')
        ->requirePermission(Permission::USERS_EDIT)
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(UpdateUserRoles::class),

    ApiEndpoint::post('api.admin.users.delete', '/api/admin/users/delete')
        ->requirePermission(Permission::USERS_DELETE)
        ->setHandler(DeleteUser::class),

    ApiEndpoint::post('api.admin.users.invite', '/api/admin/users/invite')
        ->requirePermission(Permission::USERS_CREATE)
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(CreateInvite::class),
];
