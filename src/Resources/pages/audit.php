<?php

declare(strict_types=1);

use Naf\CMS\Contracts\AuditSinkInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;

use function Naf\app;
use function Naf\I18n\t;
use function Naf\View\render;

return [
    Page::create('admin.settings.audit')
        ->setPath('/admin/settings/audit')
        ->setTitle('Audit log')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('Audit log', parent: 'settings', icon: 'stats', order: 30, key: 'settings.audit')
        ->setContent(static fn() => render('admin.settings.audit', [
            'title'        => t('Audit log'),
            'pageTitle'    => t('Audit log'),
            'pageSubtitle' => t('Review settings and translation changes.'),
            'entries'      => app()->container()->get(AuditSinkInterface::class)->latest(120),
        ])),
];
