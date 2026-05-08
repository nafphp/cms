<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Theme\StoreTheme;

return [
    ApiEndpoint::post('api.admin.themes.store', '/api/admin/themes')
        ->requireAnyPermission(Permission::THEMES_CREATE, Permission::THEMES_EDIT)
        ->setHandler(StoreTheme::class),
];
