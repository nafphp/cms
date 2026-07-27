<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Navigation\DeleteNavigation;
use src\Handlers\Api\Navigation\PublishNavigation;

return [
    ApiEndpoint::post('api.admin.navigation.publish', '/api/admin/navigation/{navigationId}/publish')
        ->requirePermission(Permission::NAVIGATION_EDIT)
        ->setHandler(PublishNavigation::class),

    ApiEndpoint::post('api.admin.navigation.delete', '/api/admin/navigation/{navigationId}/delete')
        ->requirePermission(Permission::NAVIGATION_DELETE)
        ->setHandler(DeleteNavigation::class),
];
