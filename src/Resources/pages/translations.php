<?php

declare(strict_types=1);

use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;
use Psr\Http\Message\ServerRequestInterface;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\I18n\t;
use function Naf\request;
use function Naf\View\render;

return [
    Page::create('admin.translations')
        ->setPath('/admin/settings/translations')
        ->setTitle('Languages')
        ->setLayout('admin')
        ->requirePermission(Permission::TRANSLATIONS_MANAGE)
        ->setAdminNavigation('Languages', parent: 'settings', icon: 'content', order: 20, key: 'settings.translations')
        ->setContent(static function () {
            $service  = app()->container()->get(TranslationServiceInterface::class);
            $request  = request();
            $query    = $request instanceof ServerRequestInterface ? $request->getQueryParams() : [];
            $language = cms()->languages()->normalize((string) ($query['language'] ?? cms()->settings()->currentLanguage()))
                ?? cms()->settings()->currentLanguage();
            $settingSections = app()->container()->get(SettingsServiceInterface::class)->sections('languages');

            return render('admin.settings.translations', [
                'title'           => t('Languages'),
                'pageTitle'       => t('Languages'),
                'pageSubtitle'    => t('Manage CMS languages and translation JSON files.'),
                'language'        => $language,
                'languages'       => $service->languages($language),
                'settingSections' => $settingSections,
                'entries'         => $service->entries($language),
            ]);
        }),
];
