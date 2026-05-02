<?php

declare(strict_types=1);

namespace NixCMS\Content;

final class BlockViewRegistry
{
    private const VIEWS = [
        'header' => 'page.blocks.header',
        'footer' => 'page.blocks.footer',
        'navigation' => 'page.blocks.navigation',
        'articles' => 'page.blocks.articles',
        'hero' => 'page.blocks.hero',
        'cta' => 'page.blocks.cta',
        'feature' => 'page.blocks.feature',
        'media' => 'page.blocks.media',
        'quote' => 'page.blocks.quote',
        'stats' => 'page.blocks.stats',
        'form' => 'page.blocks.form',
        'contact-form' => 'page.blocks.form',
        'heading' => 'page.blocks.heading',
        'text' => 'page.blocks.text',
        'aside' => 'page.blocks.text',
    ];

    public function viewFor(string $contentType): string
    {
        return self::VIEWS[strtolower(trim($contentType))] ?? 'page.blocks.placeholder';
    }
}
