<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Services\Admin\AdminDashboardService;
use function NixPHP\View\render;

return [
    Page::create('admin.dashboard')
        ->setPath('/admin')
        ->setTitle('Dashboard')
        ->setLayout('admin')
        ->requireAuthentication()
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setContent(static fn() => render('admin.dashboard', [
            'title' => 'Dashboard',
            'summary' => (new AdminDashboardService())->summary(),
        ])),
];
