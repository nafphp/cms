<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Navigation\DeleteNavigation;
use NixCMS\Handlers\Api\Navigation\PublishNavigation;

return [
    ApiEndpoint::post('api.admin.navigation.publish', '/api/admin/navigation/{navigationId}/publish')
        ->requirePermission(Permission::NAVIGATION_EDIT)
        ->setHandler(PublishNavigation::class),

    ApiEndpoint::post('api.admin.navigation.delete', '/api/admin/navigation/{navigationId}/delete')
        ->requirePermission(Permission::NAVIGATION_DELETE)
        ->setHandler(DeleteNavigation::class),
];
