<?php

declare(strict_types=1);

return [
    [
        'id' => 'public',
        'label' => 'Public shell',
        'layout' => 'layouts.page',
        'view' => 'page.templates.public',
        'description' => 'Simple full-document page with one main content area.',
        'blocks' => [
            ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation'],
            ['id' => 'main', 'label' => 'Main content', 'region' => 'main', 'size' => 'large'],
            ['id' => 'site_footer', 'label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer'],
        ],
    ],
    [
        'id' => 'landing',
        'label' => 'Landing page',
        'layout' => 'layouts.page',
        'view' => 'page.templates.landing',
        'description' => 'Hero-led page with a prominent intro and stacked content.',
        'blocks' => [
            ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation'],
            ['id' => 'hero', 'label' => 'Hero', 'region' => 'hero', 'size' => 'hero', 'preview' => 'hero'],
            ['id' => 'main', 'label' => 'Main content', 'region' => 'main', 'size' => 'large'],
            ['id' => 'footer', 'label' => 'Footer strip', 'region' => 'footer', 'size' => 'small', 'preview' => 'text'],
            ['id' => 'site_footer', 'label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer'],
        ],
    ],
    [
        'id' => 'editorial',
        'label' => 'Editorial index',
        'layout' => 'layouts.page',
        'view' => 'page.templates.editorial',
        'description' => 'Article-heavy overview with intro, content stream, and aside.',
        'blocks' => [
            ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation'],
            ['id' => 'intro', 'label' => 'Intro', 'region' => 'main', 'size' => 'medium', 'preview' => 'heading'],
            ['id' => 'content', 'label' => 'Content stream', 'region' => 'main', 'size' => 'large'],
            ['id' => 'aside', 'label' => 'Aside', 'region' => 'aside', 'size' => 'aside'],
            ['id' => 'site_footer', 'label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer'],
        ],
    ],
    [
        'id' => 'feature-grid',
        'label' => 'Feature grid',
        'layout' => 'layouts.page',
        'view' => 'page.templates.feature_grid',
        'description' => 'Intro block followed by three feature/content areas and a closing strip.',
        'blocks' => [
            ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation'],
            ['id' => 'intro', 'label' => 'Intro', 'region' => 'main', 'size' => 'medium', 'preview' => 'heading'],
            ['id' => 'feature_a', 'label' => 'Feature A', 'region' => 'main', 'size' => 'medium', 'preview' => 'card'],
            ['id' => 'feature_b', 'label' => 'Feature B', 'region' => 'main', 'size' => 'medium', 'preview' => 'card'],
            ['id' => 'feature_c', 'label' => 'Feature C', 'region' => 'main', 'size' => 'medium', 'preview' => 'card'],
            ['id' => 'cta', 'label' => 'CTA strip', 'region' => 'footer', 'size' => 'small', 'preview' => 'text'],
            ['id' => 'site_footer', 'label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer'],
        ],
    ],
    [
        'id' => 'article-detail',
        'label' => 'Article detail',
        'kind' => 'dynamic',
        'contentType' => 'article',
        'routePattern' => '/articles/{slug}',
        'layout' => 'layouts.page',
        'view' => 'page.templates.article_detail',
        'description' => 'Dynamic article route that resolves the article from the URL slug.',
        'blocks' => [
            ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation'],
            ['id' => 'header', 'label' => 'Article header', 'region' => 'header', 'size' => 'medium', 'accepts' => ['article-header'], 'defaultContentType' => 'article-header'],
            ['id' => 'body', 'label' => 'Article body', 'region' => 'main', 'size' => 'large', 'accepts' => ['article-body'], 'defaultContentType' => 'article-body'],
            ['id' => 'aside', 'label' => 'Reading aside', 'region' => 'aside', 'size' => 'aside'],
            ['id' => 'related', 'label' => 'Related content', 'region' => 'main', 'size' => 'small', 'accepts' => ['articles']],
            ['id' => 'site_footer', 'label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer'],
        ],
    ],
    [
        'id' => 'full-bleed',
        'label' => 'Full bleed',
        'layout' => 'layouts.page',
        'view' => 'page.templates.full_bleed',
        'description' => 'Immersive single-canvas layout for visual or campaign-style pages.',
        'blocks' => [
            ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'small', 'preview' => 'navigation'],
            ['id' => 'stage', 'label' => 'Full bleed stage', 'region' => 'hero', 'size' => 'hero'],
            ['id' => 'support', 'label' => 'Support content', 'region' => 'main', 'size' => 'medium'],
            ['id' => 'site_footer', 'label' => 'Site footer', 'region' => 'footer', 'size' => 'small', 'preview' => 'footer'],
        ],
    ],
];
