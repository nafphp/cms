<?php

declare(strict_types=1);

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Api\Navigation\DeleteNavigation;
use Naf\CMS\Handlers\Api\Navigation\PublishNavigation;

return [
    ApiEndpoint::post('api.admin.navigation.publish', '/api/admin/navigation/{navigationId}/publish')
        ->requirePermission(Permission::NAVIGATION_EDIT)
        ->setHandler(PublishNavigation::class),

    ApiEndpoint::post('api.admin.navigation.delete', '/api/admin/navigation/{navigationId}/delete')
        ->requirePermission(Permission::NAVIGATION_DELETE)
        ->setHandler(DeleteNavigation::class),
];
