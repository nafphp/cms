<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Services\Admin\AdminDashboardService;
use function NixPHP\I18n\t;
use function NixPHP\View\render;

return [
    Page::create('admin.dashboard')
        ->setPath('/admin')
        ->setTitle('Dashboard')
        ->setLayout('admin')
        ->requireAuthentication()
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('Dashboard', icon: 'stats', order: 10, key: 'dashboard')
        ->setContent(static fn() => render('admin.dashboard', [
            'title' => t('Dashboard'),
            'pageTitle' => t('Dashboard'),
            'pageSubtitle' => t('Operational overview for content, users, integrations, and system health.'),
            'summary' => (new AdminDashboardService())->summary(),
        ])),
];
