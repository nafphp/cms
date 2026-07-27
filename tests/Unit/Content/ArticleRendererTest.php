<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use src\Content\Article\Block\BlockInterface;
use src\Content\Article\BlockRegistry;
use src\Services\Content\ArticleRenderer;
use PHPUnit\Framework\TestCase;

final class ArticleRendererTest extends TestCase
{
    public function testHtmlRendersBlocksThroughRegistry(): void
    {
        $registry = new BlockRegistry();
        $article = [
            'hide_lead_image_in_content' => 0,
            'content' => json_encode([
                'blocks' => [
                    ['type' => 'header', 'data' => ['level' => 2, 'text' => 'Title']],
                    ['type' => 'paragraph', 'data' => ['text' => 'Hello']],
                ],
            ]),
        ];

        $renderer = new ArticleRenderer($article, $registry);

        self::assertSame("<h2>Title</h2>\n<p>Hello</p>", $renderer->html());
    }

    public function testHtmlSkipsFirstImageWhenLeadImageHidden(): void
    {
        $registry = new BlockRegistry();
        $article = [
            'hide_lead_image_in_content' => 1,
            'content' => json_encode([
                'blocks' => [
                    ['type' => 'image', 'data' => ['file' => ['url' => '/lead.png']]],
                    ['type' => 'paragraph', 'data' => ['text' => 'Body']],
                    ['type' => 'image', 'data' => ['file' => ['url' => '/inline.png']]],
                ],
            ]),
        ];

        $html = (new ArticleRenderer($article, $registry))->html();

        self::assertStringNotContainsString('/lead.png', $html);
        self::assertStringContainsString('/inline.png', $html);
        self::assertStringContainsString('<p>Body</p>', $html);
    }

    public function testUnknownBlockTypeFallsBackToParagraph(): void
    {
        $registry = new BlockRegistry();
        $article = [
            'content' => json_encode([
                'blocks' => [
                    ['type' => 'mystery', 'data' => ['text' => 'fallback']],
                ],
            ]),
        ];

        self::assertSame('<p>fallback</p>', (new ArticleRenderer($article, $registry))->html());
    }

    public function testCustomBlockOverridesDefault(): void
    {
        $custom = new class implements BlockInterface {
            public function render(array $data): string
            {
                return '<custom>' . ($data['text'] ?? '') . '</custom>';
            }
        };

        $registry = new BlockRegistry();
        $registry->register('paragraph', $custom::class);

        $article = [
            'content' => json_encode([
                'blocks' => [['type' => 'paragraph', 'data' => ['text' => 'X']]],
            ]),
        ];

        self::assertSame('<custom>X</custom>', (new ArticleRenderer($article, $registry))->html());
    }
}
