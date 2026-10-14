<?php

declare(strict_types=1);

use Naf\CMS\Contracts\AuditSinkInterface;
use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\I18n\t;
use function Naf\View\render;

return [
    Page::create('admin.settings')
        ->setPath('/admin/settings')
        ->setTitle('Settings')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('General', parent: 'settings', icon: 'settings', order: 10, key: 'settings.general')
        ->setContent(static fn() => render('admin.settings.index', [
            'title'        => t('Settings'),
            'pageTitle'    => t('Settings'),
            'pageSubtitle' => t('Manage application defaults and plugin-provided settings.'),
            'sections'     => app()->container()->get(SettingsServiceInterface::class)->sections('general'),
            'activeGroup'  => 'general',
        ])),
    Page::create('admin.settings.ai')
        ->setPath('/admin/settings/ai')
        ->setTitle('Local AI')
        ->setLayout('admin')
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->hideFromAdminNavigation('settings.ai')
        ->setContent(static fn() => render('admin.settings.index', [
            'title'            => t('Local AI'),
            'pageTitle'        => t('Local AI'),
            'pageSubtitle'     => t('Configure the browser-local Ollama assistant.'),
            'sections'         => app()->container()->get(SettingsServiceInterface::class)->sections('ai'),
            'activeGroup'      => 'ai',
            'routingFallbacks' => array_slice(array_values(array_filter(
                app()->container()->get(AuditSinkInterface::class)->latest(50, 'router'),
                static fn(array $entry): bool => (string) ($entry['action'] ?? '') === 'ai.routing.fallback',
            )), 0, 10),
        ])),
    Page::create('admin.settings.group')
        ->setPath('/admin/settings/group/{group}')
        ->setTitle('Settings')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->hideFromAdminNavigation('settings.general')
        ->setContent(static function (string $group) {
            $group  = strtolower(trim($group));
            $groups = cms()->settings()->groups();
            if (!isset($groups[$group]) || in_array($group, ['general', 'languages'], true)) {
                $group = 'general';
            }

            return render('admin.settings.index', [
                'title'        => t('Settings'),
                'pageTitle'    => t((string) ($groups[$group]['label'] ?? 'Settings')),
                'pageSubtitle' => t('Manage application defaults and plugin-provided settings.'),
                'sections'     => app()->container()->get(SettingsServiceInterface::class)->sections($group),
                'activeGroup'  => $group,
            ]);
        }),
];
