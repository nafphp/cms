<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Services\Settings\SettingsService;
use function NixPHP\I18n\t;
use function NixPHP\View\render;

return [
    Page::create('admin.settings')
        ->setPath('/admin/settings')
        ->setTitle('Settings')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('General', parent: 'settings', icon: 'settings', order: 10, key: 'settings.general')
        ->setContent(static fn() => render('admin.settings.index', [
            'title' => t('Settings'),
            'pageTitle' => t('Settings'),
            'pageSubtitle' => t('Manage application defaults and plugin-provided settings.'),
            'sections' => (new SettingsService())->sections('general'),
            'activeGroup' => 'general',
        ])),
    Page::create('admin.settings.group')
        ->setPath('/admin/settings/group/{group}')
        ->setTitle('Settings')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->hideFromAdminNavigation('settings.general')
        ->setContent(static function (string $group) {
            $group = strtolower(trim($group));
            $groups = \settings()->groups();
            if (!isset($groups[$group]) || in_array($group, ['general', 'languages'], true)) {
                $group = 'general';
            }

            return render('admin.settings.index', [
                'title' => t('Settings'),
                'pageTitle' => t((string) ($groups[$group]['label'] ?? 'Settings')),
                'pageSubtitle' => t('Manage application defaults and plugin-provided settings.'),
                'sections' => (new SettingsService())->sections($group),
                'activeGroup' => $group,
            ]);
        }),
];
