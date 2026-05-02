<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Enums\Language;
use NixCMS\Handlers\Page\Navigation\StoreNavigation;
use NixCMS\Page\Page;
use function NixPHP\I18n\t;
use function NixPHP\redirect;
use function NixPHP\route;
use function NixPHP\View\render;

$navigationApi = static fn() => cms()->navigation();
$pageChoices = static function (): array {
    $choices = [];
    foreach (cms()->pages()->all() as $page) {
        try {
            $path = $page->path();
        } catch (Throwable) {
            continue;
        }

        if (str_starts_with($path, '/admin') || str_contains($path, '{')) {
            continue;
        }

        $choices[] = [
            'id' => $page->id(),
            'title' => $page->title(),
            'path' => $path,
        ];
    }

    return $choices;
};

return [
    Page::create('admin.navigation')
        ->setPath('/admin/navigation')
        ->setTitle('Navigation')
        ->setLayout('admin')
        ->requirePermission(Permission::NAVIGATION_VIEW)
        ->setAdminNavigation('Navigation', parent: 'content', icon: 'navigation', order: 8, key: 'navigation')
        ->setContent(static fn() => render('admin.navigation.index', [
            'title' => t('Navigation'),
            'pageTitle' => t('Navigation'),
            'pageSubtitle' => t('Manage frontend navigation trees for headers, footers, landing pages, and PageBuilder blocks.'),
            'navigations' => $navigationApi()->summaries('frontend'),
        ])),

    Page::create('admin.navigation.create')
        ->setPath('/admin/navigation/create')
        ->setTitle('Create navigation')
        ->setLayout('admin')
        ->requirePermission(Permission::NAVIGATION_CREATE)
        ->setDataHandler(StoreNavigation::class, 'POST', Permission::NAVIGATION_CREATE)
        ->hideFromAdminNavigation('navigation')
        ->setContent(static fn() => render('admin.navigation.form', [
            'title' => t('Create navigation'),
            'pageTitle' => t('Create navigation'),
            'pageSubtitle' => t('Build a scoped frontend navigation tree.'),
            'mode' => 'create',
            'navigation' => null,
            'translations' => [],
            'languages' => array_map(static fn(Language $language): string => $language->value, Language::cases()),
            'pageChoices' => $pageChoices(),
        ])),

    Page::create('admin.navigation.edit')
        ->setPath('/admin/navigation/{navigationId}/edit')
        ->setTitle('Edit navigation')
        ->setLayout('admin')
        ->requirePermission(Permission::NAVIGATION_EDIT)
        ->setDataHandler(StoreNavigation::class, 'POST', Permission::NAVIGATION_EDIT)
        ->hideFromAdminNavigation('navigation')
        ->setContent(static function (string $navigationId) use ($navigationApi, $pageChoices) {
            $navigation = $navigationApi()->find((int) $navigationId);
            if ($navigation === null) {
                return redirect(route('admin.navigation'));
            }

            return render('admin.navigation.form', [
                'title' => t('Edit navigation'),
                'pageTitle' => t('Edit navigation'),
                'pageSubtitle' => t('Build a scoped frontend navigation tree.'),
                'mode' => 'edit',
                'navigation' => $navigation->toArray(),
                'translations' => array_map(
                    static fn($translation): array => $translation->toArray(),
                    $navigationApi()->translations($navigation->identifier())
                ),
                'languages' => array_map(static fn(Language $language): string => $language->value, Language::cases()),
                'pageChoices' => $pageChoices(),
            ]);
        }),
];
