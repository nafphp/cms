<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Settings\GetTranslations;
use src\Handlers\Api\Settings\UpdateSettings;
use src\Handlers\Api\Settings\UpdateTranslations;

return [
    ApiEndpoint::post('api.admin.settings.update', '/api/admin/settings')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(UpdateSettings::class),

    ApiEndpoint::get('api.admin.translations.show', '/api/admin/translations/{language}')
        ->requireAnyPermission(Permission::ADMIN_ACCESS, Permission::PAGES_VIEW, Permission::PAGES_CREATE, Permission::PAGES_EDIT)
        ->setHandler(GetTranslations::class),

    ApiEndpoint::post('api.admin.translations.update', '/api/admin/translations/{language}')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(UpdateTranslations::class),
];
