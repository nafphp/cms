<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Block;

use src\Content\Article\Block\CodeBlock;
use src\Content\Article\Block\EmbedBlock;
use src\Content\Article\Block\HeaderBlock;
use src\Content\Article\Block\ImageBlock;
use src\Content\Article\Block\ListBlock;
use src\Content\Article\Block\ParagraphBlock;
use src\Content\Article\Block\QuoteBlock;
use src\Content\Article\Block\TableBlock;
use PHPUnit\Framework\TestCase;

final class BlocksTest extends TestCase
{
    public function testHeaderClampsLevelBetween2And4(): void
    {
        $block = new HeaderBlock();

        self::assertSame('<h2>Hi</h2>', $block->render(['level' => 1, 'text' => 'Hi']));
        self::assertSame('<h3>Hi</h3>', $block->render(['level' => 3, 'text' => 'Hi']));
        self::assertSame('<h4>Hi</h4>', $block->render(['level' => 9, 'text' => 'Hi']));
    }

    public function testHeaderStripsDisallowedTags(): void
    {
        $block = new HeaderBlock();

        self::assertSame(
            '<h2>alert(1)<strong>bold</strong></h2>',
            $block->render(['level' => 2, 'text' => '<script>alert(1)</script><strong>bold</strong>'])
        );
    }

    public function testParagraphWrapsAndCleansInline(): void
    {
        $block = new ParagraphBlock();

        self::assertSame(
            '<p>hello <strong>world</strong></p>',
            $block->render(['text' => '<div>hello </div><strong>world</strong>'])
        );
    }

    public function testParagraphRemovesActiveInlineAttributesAndUrls(): void
    {
        $html = (new ParagraphBlock())->render([
            'text' => '<a href="javascript:alert(1)" onclick="alert(2)">link</a><strong style="color:red">safe</strong>',
        ]);

        self::assertSame('<p><a>link</a><strong>safe</strong></p>', $html);
    }

    public function testParagraphHandlesMissingText(): void
    {
        self::assertSame('<p></p>', (new ParagraphBlock())->render([]));
    }

    public function testListUnorderedAndOrdered(): void
    {
        $block = new ListBlock();

        self::assertSame(
            '<ul><li>a</li><li>b</li></ul>',
            $block->render(['style' => 'unordered', 'items' => ['a', 'b']])
        );
        self::assertSame(
            '<ol><li>a</li></ol>',
            $block->render(['style' => 'ordered', 'items' => ['a']])
        );
    }

    public function testQuoteWithCaption(): void
    {
        self::assertSame(
            '<blockquote><p>be kind</p><cite>Anon</cite></blockquote>',
            (new QuoteBlock())->render(['text' => 'be kind', 'caption' => 'Anon'])
        );
    }

    public function testQuoteWithoutCaption(): void
    {
        self::assertSame(
            '<blockquote><p>be kind</p></blockquote>',
            (new QuoteBlock())->render(['text' => 'be kind'])
        );
    }

    public function testCodeEscapesHtml(): void
    {
        self::assertSame(
            '<pre><code>&lt;script&gt;alert(1)&lt;/script&gt;</code></pre>',
            (new CodeBlock())->render(['code' => '<script>alert(1)</script>'])
        );
    }

    public function testTableRendersRows(): void
    {
        self::assertSame(
            '<table><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td></tr></tbody></table>',
            (new TableBlock())->render(['content' => [['a', 'b'], ['c']]])
        );
    }

    public function testImagePrefersFileUrlOverDataUrl(): void
    {
        $html = (new ImageBlock())->render([
            'file' => ['url' => '/img/a.png'],
            'url' => '/img/b.png',
            'caption' => 'cap',
        ]);

        self::assertStringContainsString('src="/img/a.png"', $html);
        self::assertStringContainsString('<figcaption>cap</figcaption>', $html);
    }

    public function testImageReturnsEmptyStringWhenNoUrl(): void
    {
        self::assertSame('', (new ImageBlock())->render([]));
    }

    public function testImageRejectsActiveUrlSchemes(): void
    {
        self::assertSame('', (new ImageBlock())->render(['file' => ['url' => 'javascript:alert(1)']]));
        self::assertSame('', (new ImageBlock())->render(['url' => 'data:image/svg+xml,<svg onload=alert(1)>']));
    }

    public function testEmbedRendersIframe(): void
    {
        self::assertSame(
            '<iframe src="https://example.com" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-forms allow-popups allow-same-origin allow-scripts" allowfullscreen></iframe>',
            (new EmbedBlock())->render(['embed' => 'https://example.com'])
        );
    }

    public function testEmbedReturnsEmptyStringWhenMissing(): void
    {
        self::assertSame('', (new EmbedBlock())->render([]));
    }

    public function testEmbedRejectsActiveUrlSchemes(): void
    {
        self::assertSame('', (new EmbedBlock())->render(['embed' => 'javascript:alert(1)']));
        self::assertSame('', (new EmbedBlock())->render(['embed' => 'data:text/html,<script>alert(1)</script>']));
    }
}
