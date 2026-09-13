<?php

declare(strict_types=1);

use Naf\CMS\Page\Layout;
use Naf\CMS\Page\Template;

$page = static fn(string $view): Template => Template::create($view)->setParent('page');

return [
    Layout::define('default', 'Default page', $page('default'))
        ->setDescription('Balanced page shell with a header, one main content area, and footer.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('main', 'page.blocks.empty', ['label' => 'Main content', 'region' => 'main', 'size' => 'large'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('landing', 'Landing page', $page('landing'))
        ->setDescription('Hero-led page with a prominent intro and stacked content.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('hero', 'page.blocks.empty', ['label' => 'Hero', 'region' => 'hero', 'size' => 'hero', 'preview' => 'hero'])
        ->addBlock('main', 'page.blocks.empty', ['label' => 'Main content', 'region' => 'main', 'size' => 'large'])
        ->addBlock('footer', 'page.blocks.empty', ['label' => 'Footer strip', 'region' => 'footer', 'size' => 'small', 'preview' => 'text'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('editorial', 'Editorial index', $page('editorial'))
        ->setDescription('Article-heavy overview with intro, content stream, and aside.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('intro', 'page.blocks.empty', ['label' => 'Intro', 'region' => 'main', 'size' => 'medium', 'preview' => 'heading'])
        ->addBlock('content', 'page.blocks.empty', ['label' => 'Content stream', 'region' => 'main', 'size' => 'large'])
        ->addBlock('aside', 'page.blocks.empty', ['label' => 'Aside', 'region' => 'aside', 'size' => 'aside'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('two-column', 'Two column', $page('two_column'))
        ->setDescription('Two balanced content columns with shared site chrome.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('primary', 'page.blocks.empty', ['label' => 'Primary content', 'region' => 'main', 'size' => 'medium'])
        ->addBlock('secondary', 'page.blocks.empty', ['label' => 'Secondary content', 'region' => 'main', 'size' => 'medium'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('feature-grid', 'Feature grid', $page('feature_grid'))
        ->setDescription('Intro block followed by three feature/content areas and a closing strip.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('intro', 'page.blocks.empty', ['label' => 'Intro', 'region' => 'main', 'size' => 'medium', 'preview' => 'heading'])
        ->addBlock('feature_a', 'page.blocks.empty', ['label' => 'Feature A', 'region' => 'main', 'size' => 'medium', 'preview' => 'card'])
        ->addBlock('feature_b', 'page.blocks.empty', ['label' => 'Feature B', 'region' => 'main', 'size' => 'medium', 'preview' => 'card'])
        ->addBlock('feature_c', 'page.blocks.empty', ['label' => 'Feature C', 'region' => 'main', 'size' => 'medium', 'preview' => 'card'])
        ->addBlock('cta', 'page.blocks.empty', ['label' => 'CTA strip', 'region' => 'footer', 'size' => 'small', 'preview' => 'cta', 'accepts' => ['cta', 'text'], 'defaultContentType' => 'cta'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('article-detail', 'Article detail', $page('article_detail'))
        ->setKind('dynamic')
        ->setMetadata('contentType', 'article')
        ->setMetadata('routePattern', '/articles/{slug}')
        ->setDescription('Dynamic article route that resolves the article from the URL slug.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('header', 'page.blocks.empty', ['label' => 'Article title', 'region' => 'header', 'size' => 'medium', 'accepts' => ['article-title', 'article-meta'], 'defaultContentType' => 'article-title'])
        ->addBlock('body', 'page.blocks.empty', ['label' => 'Article content', 'region' => 'main', 'size' => 'large', 'accepts' => ['article-content'], 'defaultContentType' => 'article-content'])
        ->addBlock('aside', 'page.blocks.empty', ['label' => 'Article aside', 'region' => 'aside', 'size' => 'aside', 'accepts' => ['article-meta', 'article-related', 'text', 'articles'], 'defaultContentType' => 'article-meta'])
        ->addBlock('related', 'page.blocks.empty', ['label' => 'Related articles', 'region' => 'main', 'size' => 'small', 'accepts' => ['article-related', 'articles'], 'defaultContentType' => 'article-related'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('full-bleed', 'Full bleed', $page('full_bleed'))
        ->setDescription('Immersive single-canvas layout for visual or campaign-style pages.')
        ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation', 'defaultContentType' => 'header'])
        ->addBlock('stage', 'page.blocks.empty', ['label' => 'Full bleed stage', 'region' => 'hero', 'size' => 'hero'])
        ->addBlock('support', 'page.blocks.empty', ['label' => 'Support content', 'region' => 'main', 'size' => 'medium'])
        ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer', 'defaultContentType' => 'footer']),

    Layout::define('custom', 'Custom layout', $page('custom'))
        ->setKind('custom')
        ->setDescription('Build your layout from scratch with stacked blocks.'),
];
