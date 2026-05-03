<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use PHPUnit\Framework\TestCase;

final class PageTemplateRegionTest extends TestCase
{
    public function testPageTemplatesRenderEveryConfiguredBlockRegion(): void
    {
        $templates = [
            'default' => ['site_header', 'main', 'site_footer'],
            'public' => ['site_header', 'main', 'site_footer'],
            'landing' => ['site_header', 'hero', 'main', 'footer', 'site_footer'],
            'editorial' => ['site_header', 'intro', 'content', 'aside', 'site_footer'],
            'two_column' => ['site_header', 'primary', 'secondary', 'site_footer'],
            'feature_grid' => ['site_header', 'intro', 'feature_a', 'feature_b', 'feature_c', 'cta', 'site_footer'],
            'article_detail' => ['site_header', 'header', 'body', 'aside', 'related', 'site_footer'],
            'full_bleed' => ['site_header', 'stage', 'support', 'site_footer'],
        ];

        foreach ($templates as $template => $blocks) {
            $content = file_get_contents(BASE_PATH . '/views/page/templates/' . $template . '.phtml');
            self::assertIsString($content);

            foreach ($blocks as $block) {
                self::assertStringContainsString("\$block('" . $block . "')", $content, $template . ' renders ' . $block);
            }
        }
    }
}
