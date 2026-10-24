<?php

declare(strict_types=1);

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Api\Page\DeletePage;
use Naf\CMS\Handlers\Api\Page\DeletePageLayout;
use Naf\CMS\Handlers\Api\Page\PreviewPageBlock;
use Naf\CMS\Handlers\Api\Page\StorePageLayout;

return [
    ApiEndpoint::post('api.admin.page-layouts.store', '/api/admin/page-layouts')
        ->requireAnyPermission(Permission::PAGES_CREATE, Permission::PAGES_EDIT)
        ->setHandler(StorePageLayout::class),

    ApiEndpoint::post('api.admin.pages.block-preview', '/api/admin/pages/block-preview')
        ->requireAnyPermission(Permission::PAGES_CREATE, Permission::PAGES_EDIT)
        ->setHandler(PreviewPageBlock::class),

    ApiEndpoint::delete('api.admin.page-layouts.delete', '/api/admin/page-layouts/{id}')
        ->requirePermission(Permission::PAGES_DELETE)
        ->setHandler(DeletePageLayout::class),

    ApiEndpoint::post('api.admin.pages.delete', '/api/admin/pages/{id}/delete')
        ->requirePermission(Permission::PAGES_DELETE)
        ->setHandler(DeletePage::class),
];
