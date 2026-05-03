<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Page\DeletePage;
use NixCMS\Handlers\Api\Page\StorePageLayout;

return [
    ApiEndpoint::post('api.admin.page-layouts.store', '/api/admin/page-layouts')
        ->requirePermission(Permission::PAGES_CREATE)
        ->setHandler(StorePageLayout::class),

    ApiEndpoint::post('api.admin.pages.delete', '/api/admin/pages/{id}/delete')
        ->requirePermission(Permission::PAGES_DELETE)
        ->setHandler(DeletePage::class),
];
