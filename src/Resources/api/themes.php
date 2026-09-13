<?php

declare(strict_types=1);

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Api\Theme\StoreTheme;

return [
    ApiEndpoint::post('api.admin.themes.store', '/api/admin/themes')
        ->requirePermission(Permission::THEMES_CODE_MANAGE)
        ->setHandler(StoreTheme::class),
];
