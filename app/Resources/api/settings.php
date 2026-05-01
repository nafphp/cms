<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Settings\UpdateSettings;
use NixCMS\Handlers\Api\Settings\UpdateTranslations;

return [
    ApiEndpoint::post('api.admin.settings.update', '/api/admin/settings')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(UpdateSettings::class),

    ApiEndpoint::post('api.admin.translations.update', '/api/admin/translations/{language}')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(UpdateTranslations::class),
];
