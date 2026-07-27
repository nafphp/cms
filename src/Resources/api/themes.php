<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Theme\StoreTheme;

return [
    ApiEndpoint::post('api.admin.themes.store', '/api/admin/themes')
        ->requirePermission(Permission::THEMES_CODE_MANAGE)
        ->setHandler(StoreTheme::class),
];
