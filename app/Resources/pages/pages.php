<?php

declare(strict_types=1);

use NixCMS\Content\Page\Block\PageBuilderBlockOptions;
use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Page\PageBuilderRenderer;
use NixCMS\Handlers\Page\Pages\StorePage;
use NixCMS\Page\Template;
use NixCMS\Repositories\PageLayoutRepository;
use function NixPHP\I18n\t;
use function NixPHP\request;
use function NixPHP\View\render;
use Psr\Http\Message\ResponseInterface;

$templateDefinitions = require __DIR__ . '/page_templates.php';

$navigationChoices = PageBuilderBlockOptions::navigationChoices();
$pageLanguages = cms()->settings()->languages();
$defaultPageLanguage = cms()->settings()->language();

$renderPreview = static function (array $definition, ?string $layout, bool $thumbnail = false) use ($navigationChoices): string {
    $navigationItems = (array) ($navigationChoices[0]['items'] ?? []);

    $template = Template::create($definition['view'])
        ->setLayout('layouts.page_preview')
        ->addStylesheet('/css/nixcms.css')
        ->addBodyClass($thumbnail
            ? 'dark h-screen overflow-hidden bg-slate-950 text-slate-100'
            : 'dark bg-slate-950 text-slate-100 page-builder-live-preview'
        );

    foreach ($definition['blocks'] ?? [] as $block) {
        $template->addBlock((string) $block['id'], 'page.blocks.placeholder', [
            ...$block,
            'label' => (string) $block['label'],
            'size' => (string) ($block['size'] ?? 'medium'),
            'preview' => (string) ($block['preview'] ?? 'block'),
            'thumbnail' => $thumbnail,
            'navigationItems' => $navigationItems,
        ]);
    }

    $page = Page::create('preview.' . $definition['id'])
        ->setTitle((string) $definition['label'])
        ->setTemplate($template);

    return (new PageBuilderRenderer())->render($page);
};

$layoutRepository = static fn(): PageLayoutRepository => new PageLayoutRepository();

$builderTemplateDefinitions = static fn(): array => [
    ...$templateDefinitions,
    ...$layoutRepository()->templates(),
];

$templatesForBuilder = static fn(): array => array_map(static fn(array $definition): array => [
    ...$definition,
    'previewHtml' => $renderPreview($definition, (string) ($definition['layout'] ?? 'layouts.page_preview')),
], $builderTemplateDefinitions());

$navigationOptions = PageBuilderBlockOptions::navigationOptions();

$pages = static fn(): array => cms()->pages()->summaries($builderTemplateDefinitions());

$persistedValuesForBuilder = static function (array $page): array {
    $meta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
    $seo = is_array($meta['seo'] ?? null) ? $meta['seo'] : [];
    $theme = is_array($meta['theme'] ?? null) ? $meta['theme'] : [];
    $extra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];

    return [
        'title' => (string) ($page['title'] ?? ''),
        'identifier' => (string) ($page['identifier'] ?? ''),
        'path' => (string) ($page['path'] ?? ''),
        'language' => (string) ($page['language'] ?? ''),
        'status' => (string) ($page['status'] ?? 'draft'),
        'seo.description' => (string) ($seo['description'] ?? ''),
        'seo.robots' => (string) ($seo['robots'] ?? 'index,follow'),
        'seo.canonicalUrl' => (string) ($seo['canonicalUrl'] ?? ''),
        'theme.accent' => (string) ($theme['accent'] ?? ''),
        'theme.headerBackground' => (string) ($theme['headerBackground'] ?? '#020617'),
        'theme.headerBackgroundTransparent' => filter_var($theme['headerBackgroundTransparent'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'theme.contentWidth' => (string) ($theme['contentWidth'] ?? 'template'),
        'theme.contentWidthIncludesChrome' => filter_var($theme['contentWidthIncludesChrome'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'theme.contentWidthIncludesChromeBackground' => filter_var($theme['contentWidthIncludesChromeBackground'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'defaultNavigationIdentifier' => (string) ($extra['defaultNavigationIdentifier'] ?? ''),
    ];
};

return [
    Page::create('admin.pages')
        ->setPath('/admin/pages')
        ->setTitle('Pages')
        ->setLayout('admin')
        ->requirePermission(Permission::PAGES_VIEW)
        ->setDataHandler(StorePage::class, 'POST', Permission::PAGES_CREATE)
        ->setAdminNavigation('Pages', parent: 'content', icon: 'page', order: 5, key: 'pages')
        ->setContent(static fn() => render('admin.pages.index', [
            'title' => t('Pages'),
            'pageTitle' => t('Pages'),
            'pageSubtitle' => t('Manage URL-addressable pages, templates, workflow state, and content blocks.'),
            'pages' => $pages(),
        ])),

    Page::create('admin.pages.create')
        ->setPath('/admin/pages/create')
        ->setTitle('Create page')
        ->setLayout('admin')
        ->requirePermission(Permission::PAGES_CREATE)
        ->hideFromAdminNavigation('pages')
        ->setContent(static function () use ($templatesForBuilder, $navigationChoices, $navigationOptions, $pageLanguages, $defaultPageLanguage, $persistedValuesForBuilder): string|ResponseInterface {
            $page = null;
            $id = (int) (request()->getQueryParams()['id'] ?? 0);
            if ($id > 0) {
                $page = cms()->pages()->find($id);
            }

            $initialState = is_array($page) ? [
                'id' => (int) ($page['id'] ?? 0),
                'templateId' => (string) ($page['template_id'] ?? ''),
                'blockContent' => is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
                'customLayout' => is_array($page['meta']['custom_layout'] ?? null) ? $page['meta']['custom_layout'] : [],
                'persistedValues' => $persistedValuesForBuilder($page),
            ] : [];

            $isEdit = $initialState !== [];
            $builderBlockTypes = cms()->blockTypes()->builderTypes();

            return render('admin.pages.builder', [
                'title' => $isEdit ? t('Edit page') : t('Create page'),
                'pageTitle' => $isEdit ? t('Edit page') : t('Create page'),
                'pageSubtitle' => $isEdit ? t('Update metadata, template blocks, and workflow state.') : t('Choose a template, then connect content to its blocks.'),
                'templates' => $templatesForBuilder(),
                'contentTypes' => $builderBlockTypes,
                'navigationChoices' => $navigationChoices,
                'defaultNavigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
                'languages' => $pageLanguages,
                'defaultLanguage' => $defaultPageLanguage,
                'saveUrl' => '/admin/pages',
                'overviewUrl' => '/admin/pages',
                'initialState' => $initialState,
                'mode' => $isEdit ? 'edit' : 'create',
            ]);
        }),
];
