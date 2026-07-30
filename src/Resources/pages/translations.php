<?php

declare(strict_types=1);

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Contracts\SettingsServiceInterface;
use NixPHP\CMS\Contracts\TranslationServiceInterface;
use function NixPHP\app;
use function NixPHP\I18n\t;
use function NixPHP\request;
use function NixPHP\View\render;
use function NixPHP\CMS\cms;

return [
    Page::create('admin.translations')
        ->setPath('/admin/settings/translations')
        ->setTitle('Languages')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('Languages', parent: 'settings', icon: 'content', order: 20, key: 'settings.translations')
        ->setContent(static function () {
            $service = app()->container()->get(TranslationServiceInterface::class);
            $language = cms()->languages()->normalize((string) (request()->getQueryParams()['language'] ?? cms()->settings()->currentLanguage()))
                ?? cms()->settings()->currentLanguage();
            $settingSections = app()->container()->get(SettingsServiceInterface::class)->sections('languages');

            return render('admin.settings.translations', [
                'title' => t('Languages'),
                'pageTitle' => t('Languages'),
                'pageSubtitle' => t('Manage CMS languages and translation JSON files.'),
                'language' => $language,
                'languages' => $service->languages($language),
                'settingSections' => $settingSections,
                'entries' => $service->entries($language),
            ]);
        }),
];
