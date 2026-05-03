<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Page\PageBuilderRenderer;
use NixCMS\Handlers\Page\Pages\StorePage;
use NixCMS\Page\Template;
use function NixPHP\I18n\t;
use function NixPHP\request;
use function NixPHP\View\render;
use Psr\Http\Message\ResponseInterface;

$templateDefinitions = require __DIR__ . '/page_templates.php';

$navigationChoices = static function (): array {
    $choices = [];

    foreach (cms()->navigation()->summaries('frontend') as $summary) {
        $identifier = trim((string) ($summary['identifier'] ?? ''));
        if ($identifier === '') {
            continue;
        }

        $language = trim((string) ($summary['language'] ?? ''));
        $items = cms()->navigation()->tree($identifier, $language !== '' ? $language : null);
        $status = (string) ($summary['status'] ?? 'draft');
        $priority = (int) ($summary['priority'] ?? 0);

        $choices[] = [
            'identifier' => $identifier,
            'label' => (string) (($summary['title'] ?? null) ?: $identifier),
            'language' => $language,
            'status' => $status,
            'priority' => $priority,
            'itemCount' => (int) ($summary['item_count'] ?? count($items)),
            'items' => $items,
            'rank' => match ($status) {
                'primary' => 300,
                'published' => 200,
                default => 100,
            } + $priority + match ($identifier) {
                'main', 'primary', 'header' => 25,
                default => 0,
            },
        ];
    }

    usort($choices, static fn(array $a, array $b): int => [$b['rank'], $b['itemCount'], $a['label']] <=> [$a['rank'], $a['itemCount'], $b['label']]);

    if ($choices === []) {
        $choices[] = [
            'identifier' => 'main',
            'label' => 'Main navigation',
            'language' => '',
            'status' => 'preview',
            'priority' => 0,
            'itemCount' => 4,
            'items' => [
                ['label' => 'Home', 'href' => '/', 'children' => []],
                ['label' => 'News', 'href' => '/news', 'children' => []],
                ['label' => 'Pages', 'href' => '/pages', 'children' => []],
                ['label' => 'Contact', 'href' => '/contact', 'children' => []],
            ],
            'rank' => 0,
        ];
    }

    return array_map(static function (array $choice): array {
        unset($choice['rank']);
        return $choice;
    }, $choices);
};

$navigationChoices = $navigationChoices();
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

$templatesForBuilder = static fn(): array => array_map(static fn(array $definition): array => [
    ...$definition,
    'previewHtml' => $renderPreview($definition, (string) ($definition['layout'] ?? 'layouts.page_preview')),
], $templateDefinitions);

$navigationOptions = array_map(static fn(array $navigation): array => [
    'label' => trim(sprintf(
        '%s%s%s',
        (string) $navigation['label'],
        (string) ($navigation['status'] ?? '') !== '' ? ' · ' . (string) $navigation['status'] : '',
        (int) ($navigation['priority'] ?? 0) > 0 ? ' · Prio ' . (int) $navigation['priority'] : ''
    )),
    'value' => (string) $navigation['identifier'],
], $navigationChoices);
$formOptions = \cms()->forms()->choices(false);
if ($formOptions === []) {
    $formOptions = [['label' => 'Contact form', 'value' => 'contact']];
}

$contentTypes = [
    [
        'type' => 'hero',
        'label' => 'Hero',
        'description' => 'Large first-screen block with headline, copy, CTA, and optional image.',
        'regions' => ['hero', 'header', 'main'],
        'previewTitle' => 'Hero section',
        'defaults' => [
            'eyebrow' => 'NixCMS',
            'title' => 'A clear page hero',
            'text' => 'Use this area for the main promise of the page.',
            'buttonLabel' => 'Get started',
            'buttonUrl' => '#',
            'image' => '',
        ],
        'fields' => [
            ['name' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'buttonLabel', 'label' => 'Button label', 'type' => 'text'],
            ['name' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'text'],
            ['name' => 'image', 'label' => t('Image'), 'type' => 'media'],
        ],
    ],
    [
        'type' => 'cta',
        'label' => 'Call to action',
        'description' => 'Compact conversion strip with text and one action.',
        'regions' => ['hero', 'main', 'aside', 'footer'],
        'previewTitle' => 'Call to action',
        'defaults' => [
            'title' => 'Ready to move?',
            'text' => 'Give visitors a direct next step.',
            'buttonLabel' => 'Continue',
            'buttonUrl' => '#',
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'buttonLabel', 'label' => 'Button label', 'type' => 'text'],
            ['name' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'text'],
        ],
    ],
    [
        'type' => 'feature',
        'label' => 'Feature card',
        'description' => 'Reusable card for feature grids and compact benefit sections.',
        'regions' => ['main', 'aside', 'footer'],
        'previewTitle' => 'Feature',
        'defaults' => [
            'icon' => 'N',
            'title' => 'Feature',
            'text' => 'Describe one focused capability.',
        ],
        'fields' => [
            ['name' => 'icon', 'label' => 'Icon label', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
        ],
    ],
    [
        'type' => 'media',
        'label' => 'Media',
        'description' => 'Image or visual asset with alt text and optional caption.',
        'regions' => ['hero', 'main', 'aside'],
        'previewTitle' => 'Media',
        'defaults' => [
            'src' => '',
            'alt' => '',
            'caption' => '',
        ],
        'fields' => [
            ['name' => 'src', 'label' => t('Image'), 'type' => 'media', 'altTarget' => 'alt'],
            ['name' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
        ],
    ],
    [
        'type' => 'image',
        'label' => 'Image',
        'description' => 'Single image block with aspect ratio, alt text, and caption.',
        'regions' => ['hero', 'main', 'aside', 'footer'],
        'previewTitle' => 'Image',
        'defaults' => [
            'src' => '',
            'alt' => '',
            'caption' => '',
            'aspect' => 'wide',
            'fit' => 'cover',
        ],
        'fields' => [
            ['name' => 'src', 'label' => t('Image'), 'type' => 'media', 'altTarget' => 'alt'],
            ['name' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['name' => 'aspect', 'label' => 'Aspect ratio', 'type' => 'select', 'options' => [
                ['label' => 'Wide', 'value' => 'wide'],
                ['label' => 'Square', 'value' => 'square'],
                ['label' => 'Portrait', 'value' => 'portrait'],
                ['label' => 'Original', 'value' => 'original'],
            ]],
            ['name' => 'fit', 'label' => 'Image fit', 'type' => 'select', 'options' => [
                ['label' => 'Cover', 'value' => 'cover'],
                ['label' => 'Contain', 'value' => 'contain'],
            ]],
        ],
    ],
    [
        'type' => 'embed',
        'label' => 'External embed',
        'description' => 'Embed an external page in an iframe when the target allows it.',
        'regions' => ['main', 'aside', 'footer'],
        'previewTitle' => 'External embed',
        'defaults' => [
            'url' => 'https://example.com',
            'title' => 'External content',
            'caption' => '',
            'height' => 420,
        ],
        'fields' => [
            ['name' => 'url', 'label' => 'Embed URL', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['name' => 'height', 'label' => 'Height', 'type' => 'number', 'min' => 220, 'max' => 900, 'step' => 20],
        ],
    ],
    [
        'type' => 'map',
        'label' => 'Map',
        'description' => 'Embed a map from an address, place query, or custom map embed URL.',
        'regions' => ['main', 'aside', 'footer'],
        'previewTitle' => 'Map',
        'defaults' => [
            'query' => '',
            'embedUrl' => '',
            'title' => 'Find us',
            'caption' => '',
            'height' => 360,
        ],
        'fields' => [
            ['name' => 'query', 'label' => 'Address or place', 'type' => 'text'],
            ['name' => 'embedUrl', 'label' => 'Custom embed URL', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['name' => 'height', 'label' => 'Height', 'type' => 'number', 'min' => 220, 'max' => 760, 'step' => 20],
        ],
    ],
    [
        'type' => 'quote',
        'label' => 'Quote',
        'description' => 'Pull quote, testimonial, or editorial statement.',
        'regions' => ['main', 'aside'],
        'previewTitle' => 'Quote',
        'defaults' => [
            'quote' => 'A focused quote or testimonial belongs here.',
            'source' => '',
        ],
        'fields' => [
            ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea'],
            ['name' => 'source', 'label' => 'Source', 'type' => 'text'],
        ],
    ],
    [
        'type' => 'stats',
        'label' => 'Stats',
        'description' => 'Three compact metrics for proof points and summaries.',
        'regions' => ['main', 'footer'],
        'previewTitle' => 'Stats',
        'defaults' => [
            'valueA' => '12',
            'labelA' => 'Items',
            'valueB' => '98%',
            'labelB' => 'Quality',
            'valueC' => '24/7',
            'labelC' => 'Access',
        ],
    ],
    [
        'type' => 'chart',
        'label' => t('Diagram'),
        'description' => t('Configurable chart block for metrics, comparisons, shares, and editorial data.'),
        'regions' => ['hero', 'main', 'aside', 'footer'],
        'previewTitle' => t('Diagram'),
        'defaults' => [
            'title' => t('Useful metrics'),
            'subtitle' => t('A clear visual summary.'),
            'chartType' => 'metric-bars',
            'datasetLabel' => t('Value'),
            'metricCount' => 3,
            'height' => 320,
            'showLegend' => true,
            'showLabels' => true,
            'smoothLine' => true,
            'label1' => t('Draft'),
            'value1' => 12,
            'color1' => '#3b82f6',
            'label2' => t('Review'),
            'value2' => 7,
            'color2' => '#f59e0b',
            'label3' => t('Published'),
            'value3' => 24,
            'color3' => '#22c55e',
            'label4' => t('Scheduled'),
            'value4' => 5,
            'color4' => '#8b5cf6',
            'label5' => t('Archived'),
            'value5' => 3,
            'color5' => '#64748b',
            'label6' => t('Declined'),
            'value6' => 2,
            'color6' => '#ef4444',
        ],
        'fields' => [
            ['name' => 'title', 'label' => t('Title'), 'type' => 'text'],
            ['name' => 'subtitle', 'label' => t('Subtitle'), 'type' => 'textarea'],
            ['name' => 'chartType', 'label' => t('Type'), 'type' => 'select', 'options' => [
                ['label' => t('Metric bars'), 'value' => 'metric-bars'],
                ['label' => t('Bar'), 'value' => 'bar'],
                ['label' => t('Line'), 'value' => 'line'],
                ['label' => t('Doughnut'), 'value' => 'doughnut'],
                ['label' => t('Pie'), 'value' => 'pie'],
            ]],
            ['name' => 'datasetLabel', 'label' => t('Metric group label'), 'type' => 'text'],
            ['name' => 'metricCount', 'label' => t('Number of metrics'), 'type' => 'number', 'min' => 1, 'max' => 6, 'step' => 1],
            ['name' => 'height', 'label' => t('Chart height'), 'type' => 'number', 'min' => 220, 'max' => 720, 'step' => 20],
            ['name' => 'showLegend', 'label' => t('Show legend'), 'type' => 'checkbox'],
            ['name' => 'showLabels', 'label' => t('Show pie labels'), 'type' => 'checkbox'],
            ['name' => 'smoothLine', 'label' => t('Smooth line'), 'type' => 'checkbox'],
            ['name' => 'label1', 'label' => t('Metric 1 label'), 'type' => 'text'],
            ['name' => 'value1', 'label' => t('Metric 1 value'), 'type' => 'number', 'step' => 0.1],
            ['name' => 'color1', 'label' => t('Metric 1 color'), 'type' => 'color'],
            ['name' => 'label2', 'label' => t('Metric 2 label'), 'type' => 'text'],
            ['name' => 'value2', 'label' => t('Metric 2 value'), 'type' => 'number', 'step' => 0.1],
            ['name' => 'color2', 'label' => t('Metric 2 color'), 'type' => 'color'],
            ['name' => 'label3', 'label' => t('Metric 3 label'), 'type' => 'text'],
            ['name' => 'value3', 'label' => t('Metric 3 value'), 'type' => 'number', 'step' => 0.1],
            ['name' => 'color3', 'label' => t('Metric 3 color'), 'type' => 'color'],
            ['name' => 'label4', 'label' => t('Metric 4 label'), 'type' => 'text'],
            ['name' => 'value4', 'label' => t('Metric 4 value'), 'type' => 'number', 'step' => 0.1],
            ['name' => 'color4', 'label' => t('Metric 4 color'), 'type' => 'color'],
            ['name' => 'label5', 'label' => t('Metric 5 label'), 'type' => 'text'],
            ['name' => 'value5', 'label' => t('Metric 5 value'), 'type' => 'number', 'step' => 0.1],
            ['name' => 'color5', 'label' => t('Metric 5 color'), 'type' => 'color'],
            ['name' => 'label6', 'label' => t('Metric 6 label'), 'type' => 'text'],
            ['name' => 'value6', 'label' => t('Metric 6 value'), 'type' => 'number', 'step' => 0.1],
            ['name' => 'color6', 'label' => t('Metric 6 color'), 'type' => 'color'],
        ],
    ],
    [
        'type' => 'form',
        'label' => 'Form',
        'description' => 'Render a reusable form created under Content > Forms.',
        'regions' => ['main', 'aside', 'footer'],
        'previewTitle' => 'Form',
        'defaults' => [
            'title' => 'Contact',
            'intro' => 'Send us a message.',
            'formIdentifier' => (string) ($formOptions[0]['value'] ?? 'contact'),
            'submitLabel' => 'Send',
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'intro', 'label' => 'Intro', 'type' => 'textarea'],
            ['name' => 'formIdentifier', 'label' => 'Form', 'type' => 'select', 'options' => $formOptions],
            ['name' => 'submitLabel', 'label' => 'Submit label', 'type' => 'text'],
        ],
    ],
    [
        'type' => 'contact-form',
        'label' => 'Contact form',
        'description' => 'Contact-oriented preset backed by the reusable Form content type.',
        'regions' => ['main', 'aside', 'footer'],
        'previewTitle' => 'Contact form',
        'defaults' => [
            'title' => 'Contact',
            'intro' => 'Tell us what you need.',
            'formIdentifier' => (string) ($formOptions[0]['value'] ?? 'contact'),
            'submitLabel' => 'Send message',
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'intro', 'label' => 'Intro', 'type' => 'textarea'],
            ['name' => 'formIdentifier', 'label' => 'Form', 'type' => 'select', 'options' => $formOptions],
            ['name' => 'submitLabel', 'label' => 'Submit label', 'type' => 'text'],
        ],
    ],
    [
        'type' => 'header',
        'label' => 'Header',
        'description' => 'Site header with logo text and a selected navigation tree.',
        'regions' => ['header'],
        'previewTitle' => 'Site header',
        'defaults' => [
            'logoText' => '',
            'navigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
            'presentation' => 'horizontal',
        ],
        'fields' => [
            ['name' => 'logoText', 'label' => 'Logo text', 'type' => 'text'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => $navigationOptions],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['horizontal', 'compact']],
        ],
    ],
    [
        'type' => 'footer',
        'label' => 'Footer',
        'description' => 'Site footer with optional navigation and small imprint text.',
        'regions' => ['footer'],
        'previewTitle' => 'Site footer',
        'defaults' => [
            'text' => 'Powered by NixCMS',
            'navigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
            'presentation' => 'compact',
        ],
        'fields' => [
            ['name' => 'text', 'label' => 'Footer text', 'type' => 'text'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => $navigationOptions],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['compact', 'horizontal']],
        ],
    ],
    [
        'type' => 'aside',
        'label' => 'Aside',
        'description' => 'Sidebar block for contextual copy and vertical navigation.',
        'regions' => ['aside'],
        'previewTitle' => 'Aside',
        'defaults' => [
            'title' => 'Explore',
            'text' => 'Contextual links and supporting content.',
            'navigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => $navigationOptions],
        ],
    ],
    [
        'type' => 'heading',
        'label' => 'Headline',
        'description' => 'Simple title text for hero, intro, or section headings.',
        'regions' => ['header', 'hero', 'main', 'aside', 'footer'],
        'previewTitle' => 'Neue Überschrift',
        'defaults' => [
            'text' => 'Neue Überschrift',
            'level' => 'h2',
            'align' => 'left',
        ],
    ],
    [
        'type' => 'text',
        'label' => 'Text block',
        'description' => 'Plain copy for short intro, aside, or CTA text.',
        'regions' => ['header', 'hero', 'main', 'aside', 'footer'],
        'previewTitle' => 'Kurzer Text',
        'defaults' => [
            'text' => 'Ein kurzer Textabschnitt für diese Position.',
            'tone' => 'body',
            'align' => 'left',
        ],
    ],
    [
        'type' => 'articles',
        'label' => 'Article collection',
        'description' => 'Render a selected article query as list, cards, or compact links.',
        'regions' => ['main', 'aside', 'footer'],
        'previewTitle' => 'Latest articles',
        'defaults' => [
            'source' => 'latest',
            'limit' => 5,
            'presentation' => 'list',
            'language' => 'current',
            'detailPath' => '/articles/{slug}',
        ],
        'fields' => [
            ['name' => 'source', 'label' => 'Source', 'type' => 'select', 'options' => ['latest', 'featured', 'category', 'related']],
            ['name' => 'limit', 'label' => 'Limit', 'type' => 'number'],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['list', 'cards', 'compact']],
            ['name' => 'language', 'label' => 'Language', 'type' => 'select', 'options' => ['current', 'de', 'en', 'fr', 'es', 'it']],
            ['name' => 'detailPath', 'label' => 'Detail URL pattern', 'type' => 'text'],
        ],
    ],
    [
        'type' => 'article-header',
        'label' => 'Current article header',
        'description' => 'Render the resolved article title, teaser, language, and publish metadata.',
        'regions' => ['header'],
        'contentTypes' => ['article'],
        'templateKinds' => ['dynamic'],
        'previewTitle' => 'Resolved article header',
        'defaults' => [
            'showTeaser' => true,
            'showMeta' => true,
        ],
        'fields' => [
            ['name' => 'showTeaser', 'label' => 'Show teaser', 'type' => 'checkbox'],
            ['name' => 'showMeta', 'label' => 'Show meta', 'type' => 'checkbox'],
        ],
    ],
    [
        'type' => 'article-body',
        'label' => 'Current article body',
        'description' => 'Render the Editor.js body of the article resolved from the current URL.',
        'regions' => ['main'],
        'contentTypes' => ['article'],
        'templateKinds' => ['dynamic'],
        'previewTitle' => 'Resolved article body',
        'defaults' => [
            'hideLeadImageInContent' => true,
        ],
        'fields' => [
            ['name' => 'hideLeadImageInContent', 'label' => 'Hide lead image in body', 'type' => 'checkbox'],
        ],
    ],
    [
        'type' => 'navigation',
        'label' => 'Navigation',
        'description' => 'Render a stored navigation tree in a matching page region.',
        'regions' => ['header', 'aside', 'footer'],
        'previewTitle' => 'Main navigation',
        'defaults' => [
            'identifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
            'presentation' => 'horizontal',
            'language' => 'current',
        ],
        'fields' => [
            ['name' => 'identifier', 'label' => 'Navigation', 'type' => 'select', 'options' => $navigationOptions],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['horizontal', 'vertical', 'compact']],
            ['name' => 'language', 'label' => 'Language', 'type' => 'select', 'options' => ['current', 'de', 'en', 'fr', 'es', 'it']],
        ],
    ],
];

$pages = cms()->pages()->summaries($templateDefinitions);

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
        'theme.contentWidth' => (string) ($theme['contentWidth'] ?? 'template'),
        'theme.contentWidthIncludesChrome' => filter_var($theme['contentWidthIncludesChrome'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
            'pages' => $pages,
        ])),

    Page::create('admin.pages.create')
        ->setPath('/admin/pages/create')
        ->setTitle('Create page')
        ->setLayout('admin')
        ->requirePermission(Permission::PAGES_CREATE)
        ->hideFromAdminNavigation('pages')
        ->setContent(static function () use ($templatesForBuilder, $contentTypes, $navigationChoices, $navigationOptions, $pageLanguages, $defaultPageLanguage, $persistedValuesForBuilder): string|ResponseInterface {
            $page = null;
            $id = (int) (request()->getQueryParams()['id'] ?? 0);
            if ($id > 0) {
                $page = cms()->pages()->find($id);
            }

            $initialState = is_array($page) ? [
                'id' => (int) ($page['id'] ?? 0),
                'templateId' => (string) ($page['template_id'] ?? ''),
                'blockContent' => is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
                'persistedValues' => $persistedValuesForBuilder($page),
            ] : [];

            $isEdit = $initialState !== [];

            return render('admin.pages.builder', [
                'title' => $isEdit ? t('Edit page') : t('Create page'),
                'pageTitle' => $isEdit ? t('Edit page') : t('Create page'),
                'pageSubtitle' => $isEdit ? t('Update metadata, template blocks, and workflow state.') : t('Choose a template, then connect content to its blocks.'),
                'templates' => $templatesForBuilder(),
                'contentTypes' => $contentTypes,
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
