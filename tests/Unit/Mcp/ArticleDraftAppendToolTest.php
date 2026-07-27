<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use NixPHP\CMS\Enums\ArticleStatus;
use NixPHP\CMS\Mcp\Tools\ArticleDraftAppendTool;
use NixPHP\CMS\Models\Article;
use Tests\Support\NixPHPTestCase;

final class ArticleDraftAppendToolTest extends NixPHPTestCase
{
    public function testAppendsPlainTextToExistingDraftByArticleId(): void
    {
        $article = $this->mockArticle(
            ['id' => 5, 'tags' => ['MCP', 'Intern']],
            $this->mockArticleVersion([
                'id' => 12,
                'article_id' => 5,
                'language' => 'de',
                'status' => ArticleStatus::DRAFT,
                'title' => 'Das ist ein Test.',
                'slug' => 'das-ist-ein-test',
                'content' => [
                    $this->mockParagraphBlock('Bestehender Absatz.'),
                ],
            ])
        );
        $captured = [];

        $tool = new ArticleDraftAppendTool(
            findArticle: static fn(int $articleId, string $language): ?Article => $articleId === 5 && $language === 'de' ? $article : null,
            saveDraft: function (int $articleId, array $payload, ?int $authorId) use (&$captured): array {
                $captured = ['articleId' => $articleId, 'payload' => $payload, 'authorId' => $authorId];
                return ['articleId' => $articleId, 'articleVersionId' => 12];
            },
        );

        $result = $tool->handle([
            'article_id' => 5,
            'language' => 'de',
            'body' => "Neue Info.\n\nNoch eine Info.",
            'author_id' => 9,
        ]);

        self::assertTrue($result['success']);
        self::assertSame(5, $result['articleId']);
        self::assertSame(12, $captured['payload']['article_version_id']);
        self::assertSame(9, $captured['authorId']);
        self::assertSame(['MCP', 'Intern'], $captured['payload']['tags']);
        self::assertSame([
            ['type' => 'paragraph', 'data' => ['text' => 'Bestehender Absatz.']],
            ['type' => 'paragraph', 'data' => ['text' => 'Neue Info.']],
            ['type' => 'paragraph', 'data' => ['text' => 'Noch eine Info.']],
        ], $captured['payload']['content']['blocks']);
        self::assertSame(2, $result['appended_blocks']);
    }

    public function testResolvesArticleByReferenceWhenIdIsMissing(): void
    {
        $article = $this->mockArticle(
            ['id' => 7, 'tags' => ['Notiz']],
            $this->mockArticleVersion([
                'id' => 21,
                'article_id' => 7,
                'language' => 'de',
                'status' => ArticleStatus::DRAFT,
                'title' => 'Merkliste unterwegs',
                'slug' => 'merkliste-unterwegs',
            ])
        );

        $tool = new ArticleDraftAppendTool(
            findArticle: static fn(int $articleId, string $language): ?Article => $articleId === 7 ? $article : null,
            saveDraft: static fn(int $articleId, array $payload, ?int $authorId): array => [
                'articleId' => $articleId,
                'articleVersionId' => 21,
            ],
            resolveArticleId: static fn(string $reference, string $language): ?int => $reference === 'Merkliste unterwegs' ? 7 : null,
        );

        $result = $tool->handle([
            'reference' => 'Merkliste unterwegs',
            'language' => 'de',
            'body' => 'Neue Notiz von unterwegs.',
        ]);

        self::assertSame(7, $result['articleId']);
    }
}
