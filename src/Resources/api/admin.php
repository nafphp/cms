<?php

declare(strict_types=1);

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Api\Admin\GetRolesOverview;
use NixPHP\CMS\Handlers\Api\Admin\GetSummary;
use NixPHP\CMS\Handlers\Api\Admin\ListUsers;

return [
    ApiEndpoint::get('api.admin.summary', '/api/admin/summary')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(GetSummary::class),

    ApiEndpoint::get('api.admin.users', '/api/admin/users')
        ->requirePermission(Permission::USERS_VIEW)
        ->setHandler(ListUsers::class),

    ApiEndpoint::get('api.admin.roles', '/api/admin/roles')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setHandler(GetRolesOverview::class),
];
