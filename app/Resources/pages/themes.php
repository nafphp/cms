<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Handlers\Page\Theme\StoreTheme;
use NixCMS\Page\Page;
use NixCMS\Repositories\ThemeRepository;
use function NixPHP\I18n\t;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\route;
use function NixPHP\View\render;

$repository = static fn(): ThemeRepository => new ThemeRepository();

return [
    Page::create('admin.themes')
        ->setPath('/admin/themes')
        ->setTitle('Themes')
        ->setLayout('admin')
        ->requirePermission(Permission::THEMES_VIEW)
        ->setAdminNavigation('Themes', parent: 'content', icon: 'paint', order: 8, key: 'themes')
        ->setContent(static fn() => render('admin.themes.index', [
            'title' => t('Themes'),
            'pageSubtitle' => t('Manage frontend theme assets, surfaces, and editor exports.'),
            'themes' => $repository()->summaries(),
        ])),

    Page::create('admin.themes.create')
        ->setPath('/admin/themes/create')
        ->setTitle('Create theme')
        ->setLayout('admin')
        ->requirePermission(Permission::THEMES_CREATE)
        ->setDataHandler(StoreTheme::class, 'POST', Permission::THEMES_CREATE)
        ->hideFromAdminNavigation('themes')
        ->setContent(static fn() => render('admin.themes.form', [
            'title' => t('Create theme'),
            'pageSubtitle' => t('Define surfaces plus CSS and JavaScript for a frontend theme.'),
            'theme' => null,
            'error' => (string) (request()->getQueryParams()['error'] ?? ''),
        ])),

    Page::create('admin.themes.edit')
        ->setPath('/admin/themes/{themeId}/edit')
        ->setTitle('Edit theme')
        ->setLayout('admin')
        ->requirePermission(Permission::THEMES_EDIT)
        ->setDataHandler(StoreTheme::class, 'POST', Permission::THEMES_EDIT)
        ->hideFromAdminNavigation('themes')
        ->setContent(static function (string $themeId) use ($repository) {
            $theme = $repository()->find((int) $themeId);
            if ($theme === null) {
                return redirect(route('admin.themes'));
            }

            return render('admin.themes.form', [
                'title' => t('Edit theme'),
                'pageSubtitle' => t('Define surfaces plus CSS and JavaScript for a frontend theme.'),
                'theme' => $theme,
                'error' => (string) (request()->getQueryParams()['error'] ?? ''),
            ]);
        }),
];
