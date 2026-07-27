<?php

declare(strict_types=1);

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Repositories\AuditLogRepository;
use function NixPHP\I18n\t;
use function NixPHP\View\render;

return [
    Page::create('admin.settings.audit')
        ->setPath('/admin/settings/audit')
        ->setTitle('Audit log')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('Audit log', parent: 'settings', icon: 'stats', order: 30, key: 'settings.audit')
        ->setContent(static fn() => render('admin.settings.audit', [
            'title' => t('Audit log'),
            'pageTitle' => t('Audit log'),
            'pageSubtitle' => t('Review settings and translation changes.'),
            'entries' => (new AuditLogRepository())->latest(120),
        ])),
];
