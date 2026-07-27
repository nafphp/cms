<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use NixPHP\CMS\Mcp\Tools\ArticleDraftCreateTool;
use PHPUnit\Framework\TestCase;

final class ArticleDraftCreateToolTest extends TestCase
{
    public function testCreatesDraftPayloadFromPlainBody(): void
    {
        $captured = [];
        $tool = new ArticleDraftCreateTool(
            function (array $payload, ?int $authorId) use (&$captured): array {
                $captured = ['payload' => $payload, 'authorId' => $authorId];
                return ['articleId' => 42, 'articleVersionId' => 84];
            }
        );

        $result = $tool->handle([
            'title' => 'A Useful Draft Title',
            'language' => 'DE',
            'body' => "First paragraph.\n\nSecond paragraph.",
            'tags' => 'CMS, AI, CMS',
            'category' => 'Product',
            'author_id' => 7,
            'show_teaser' => true,
        ]);

        self::assertTrue($result['success']);
        self::assertSame(42, $result['articleId']);
        self::assertSame(84, $result['articleVersionId']);
        self::assertSame('draft', $result['status']);
        self::assertSame('de', $captured['payload']['language']);
        self::assertSame('a-useful-draft-title', $captured['payload']['slug']);
        self::assertSame('Product', $captured['payload']['category']);
        self::assertSame(['CMS', 'AI'], $captured['payload']['tags']);
        self::assertTrue($captured['payload']['show_teaser']);
        self::assertSame(7, $captured['authorId']);
        self::assertSame([
            ['type' => 'paragraph', 'data' => ['text' => 'First paragraph.']],
            ['type' => 'paragraph', 'data' => ['text' => 'Second paragraph.']],
        ], $captured['payload']['content']['blocks']);
    }

    public function testCreatesDraftPayloadFromEditorBlocks(): void
    {
        $captured = [];
        $tool = new ArticleDraftCreateTool(
            function (array $payload, ?int $authorId) use (&$captured): array {
                $captured = $payload;
                return ['articleId' => 10, 'articleVersionId' => 20];
            }
        );

        $tool->handle([
            'title' => 'Another Useful Draft',
            'slug' => 'custom-draft',
            'language' => 'en',
            'blocks' => [
                ['type' => 'header', 'data' => ['text' => 'Intro', 'level' => 2]],
                ['type' => '', 'data' => ['text' => 'Ignored']],
                'not-a-block',
            ],
        ]);

        self::assertSame('custom-draft', $captured['slug']);
        self::assertSame([
            ['type' => 'header', 'data' => ['text' => 'Intro', 'level' => 2]],
        ], $captured['content']['blocks']);
    }

    public function testRejectsDraftWithoutContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ArticleDraftCreateTool(
            static fn(array $payload, ?int $authorId): array => ['articleId' => 1, 'articleVersionId' => 2]
        ))->handle([
            'title' => 'A Useful Draft Title',
            'language' => 'en',
        ]);
    }
}
