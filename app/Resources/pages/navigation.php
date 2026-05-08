<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Handlers\Page\Navigation\StoreNavigation;
use NixCMS\Page\Page;
use NixCMS\Services\Settings\TranslationFileService;
use function NixPHP\I18n\t;
use function NixPHP\redirect;
use function NixPHP\route;
use function NixPHP\View\render;
use function NixCMS\cms;

$navigationApi = static fn() => cms()->navigation();
$translationSuggestions = static function (): array {
    $service = new TranslationFileService();
    $languages = array_values(array_unique([
        ...cms()->settings()->languages(),
        ...array_map(
            static fn(array $language): string => (string) ($language['code'] ?? ''),
            $service->languages(cms()->settings()->currentLanguage()),
        ),
    ]));

    $byLanguage = [];
    foreach ($languages as $language) {
        $language = cms()->languages()->normalize($language);
        if ($language === null) {
            continue;
        }

        $entries = $service->entries($language);
        $byLanguage[$language] = array_map(
            static fn(string $key, string $value): array => ['key' => $key, 'value' => $value],
            array_keys($entries),
            array_values($entries),
        );
    }

    return $byLanguage;
};
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
            'languages' => cms()->settings()->languages(),
            'pageChoices' => $pageChoices(),
            'translationSuggestions' => $translationSuggestions(),
        ])),

    Page::create('admin.navigation.edit')
        ->setPath('/admin/navigation/{navigationId}/edit')
        ->setTitle('Edit navigation')
        ->setLayout('admin')
        ->requirePermission(Permission::NAVIGATION_EDIT)
        ->setDataHandler(StoreNavigation::class, 'POST', Permission::NAVIGATION_EDIT)
        ->hideFromAdminNavigation('navigation')
        ->setContent(static function (string $navigationId) use ($navigationApi, $pageChoices, $translationSuggestions) {
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
                'languages' => cms()->settings()->languages(),
                'pageChoices' => $pageChoices(),
                'translationSuggestions' => $translationSuggestions(),
            ]);
        }),
];
