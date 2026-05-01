<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Services\Settings\SettingsService;
use NixCMS\Services\Settings\TranslationFileService;
use function NixPHP\I18n\t;
use function NixPHP\request;
use function NixPHP\View\render;

return [
    Page::create('admin.translations')
        ->setPath('/admin/settings/translations')
        ->setTitle('Languages')
        ->setLayout('admin')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setAdminNavigation('Languages', parent: 'settings', icon: 'content', order: 20, key: 'settings.translations')
        ->setContent(static function () {
            $service = new TranslationFileService();
            $language = strtolower(trim((string) (request()->getQueryParams()['language'] ?? \cms()->settings()->currentLanguage())));
            $settingSections = (new SettingsService())->sections('languages');

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
