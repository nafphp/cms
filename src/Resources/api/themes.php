<?php

declare(strict_types=1);

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Api\Theme\StoreTheme;

return [
    ApiEndpoint::post('api.admin.themes.store', '/api/admin/themes')
        ->requirePermission(Permission::THEMES_CODE_MANAGE)
        ->setHandler(StoreTheme::class),
];
