<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Page\DeletePage;
use src\Handlers\Api\Page\DeletePageLayout;
use src\Handlers\Api\Page\StorePageLayout;

return [
    ApiEndpoint::post('api.admin.page-layouts.store', '/api/admin/page-layouts')
        ->requireAnyPermission(Permission::PAGES_CREATE, Permission::PAGES_EDIT)
        ->setHandler(StorePageLayout::class),

    ApiEndpoint::delete('api.admin.page-layouts.delete', '/api/admin/page-layouts/{id}')
        ->requirePermission(Permission::PAGES_DELETE)
        ->setHandler(DeletePageLayout::class),

    ApiEndpoint::post('api.admin.pages.delete', '/api/admin/pages/{id}/delete')
        ->requirePermission(Permission::PAGES_DELETE)
        ->setHandler(DeletePage::class),
];
