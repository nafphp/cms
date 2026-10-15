<?php

declare(strict_types=1);

use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Page\Theme\StoreTheme;
use Naf\CMS\Page\Page;
use Naf\CMS\Repositories\ThemeRepository;
use Psr\Http\Message\ServerRequestInterface;

use function Naf\I18n\t;
use function Naf\redirect;
use function Naf\request;
use function Naf\route;
use function Naf\View\render;

$repository = static fn(): ThemeRepository => new ThemeRepository();

return [
    Page::create('admin.themes')
        ->setPath('/admin/themes')
        ->setTitle('Themes')
        ->setLayout('admin')
        ->requirePermission(Permission::THEMES_VIEW)
        ->setAdminNavigation('Themes', parent: 'content', icon: 'paint', order: 8, key: 'themes')
        ->setContent(static fn() => render('admin.themes.index', [
            'title'        => t('Themes'),
            'pageSubtitle' => t('Manage frontend theme assets, surfaces, and editor exports.'),
            'themes'       => $repository()->summaries(),
        ])),

    Page::create('admin.themes.create')
        ->setPath('/admin/themes/create')
        ->setTitle('Create theme')
        ->setLayout('admin')
        ->requirePermission(Permission::THEMES_CREATE)
        ->requirePermission(Permission::THEMES_CODE_MANAGE)
        ->setDataHandler(StoreTheme::class, 'POST', Permission::THEMES_CREATE)
        ->hideFromAdminNavigation('themes')
        ->setContent(static function () {
            $request = request();
            $query   = $request instanceof ServerRequestInterface ? $request->getQueryParams() : [];

            return render('admin.themes.form', [
                'title'        => t('Create theme'),
                'pageSubtitle' => t('Define surfaces and CSS for a frontend theme.'),
                'theme'        => null,
                'error'        => (string) ($query['error'] ?? ''),
            ]);
        }),

    Page::create('admin.themes.edit')
        ->setPath('/admin/themes/{themeId}/edit')
        ->setTitle('Edit theme')
        ->setLayout('admin')
        ->requirePermission(Permission::THEMES_EDIT)
        ->requirePermission(Permission::THEMES_CODE_MANAGE)
        ->setDataHandler(StoreTheme::class, 'POST', Permission::THEMES_EDIT)
        ->hideFromAdminNavigation('themes')
        ->setContent(static function (string $themeId) use ($repository) {
            $request = request();
            $query   = $request instanceof ServerRequestInterface ? $request->getQueryParams() : [];
            $theme   = $repository()->find((int) $themeId);
            if ($theme === null) {
                return redirect(route('admin.themes'));
            }

            return render('admin.themes.form', [
                'title'        => t('Edit theme'),
                'pageSubtitle' => t('Define surfaces and CSS for a frontend theme.'),
                'theme'        => $theme,
                'error'        => (string) ($query['error'] ?? ''),
            ]);
        }),
];
