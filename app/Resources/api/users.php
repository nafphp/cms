<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\User\CreateInvite;
use NixCMS\Handlers\Api\User\DeleteUser;
use NixCMS\Handlers\Api\User\ListUsers;
use NixCMS\Handlers\Api\User\StoreUser;
use NixCMS\Handlers\Api\User\UpdateUser;
use NixCMS\Handlers\Api\User\UpdateUserPassword;
use NixCMS\Handlers\Api\User\UpdateUserRoles;

return [
    ApiEndpoint::get('api.admin.users.list', '/api/admin/users/list')
        ->requirePermission(Permission::USERS_VIEW)
        ->setHandler(ListUsers::class),

    ApiEndpoint::post('api.admin.users.store', '/api/admin/users/store')
        ->requirePermission(Permission::USERS_CREATE)
        ->setHandler(StoreUser::class),

    ApiEndpoint::post('api.admin.users.update', '/api/admin/users/update')
        ->requirePermission(Permission::USERS_EDIT)
        ->setHandler(UpdateUser::class),

    ApiEndpoint::post('api.admin.users.password', '/api/admin/users/password')
        ->requirePermission(Permission::USERS_EDIT)
        ->setHandler(UpdateUserPassword::class),

    ApiEndpoint::post('api.admin.users.roles', '/api/admin/users/roles')
        ->requirePermission(Permission::USERS_EDIT)
        ->setHandler(UpdateUserRoles::class),

    ApiEndpoint::post('api.admin.users.delete', '/api/admin/users/delete')
        ->requirePermission(Permission::USERS_DELETE)
        ->setHandler(DeleteUser::class),

    ApiEndpoint::post('api.admin.users.invite', '/api/admin/users/invite')
        ->requirePermission(Permission::USERS_CREATE)
        ->setHandler(CreateInvite::class),
];
