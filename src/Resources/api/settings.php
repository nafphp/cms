<?php

declare(strict_types=1);

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Api\Settings\GetTranslations;
use NixPHP\CMS\Handlers\Api\Settings\UpdateSettings;
use NixPHP\CMS\Handlers\Api\Settings\UpdateTranslations;

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
