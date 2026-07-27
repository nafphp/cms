<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use src\Enums\ArticleStatus;
use src\Enums\Language;
use Tests\Support\NixPHPTestCase;

final class NixPHPTestCaseTest extends NixPHPTestCase
{
    public function testMockArticleCreatesArticleWithCurrentVersion(): void
    {
        $article = $this->mockArticle([
            'id' => 42,
            'tags' => ['cms', 'testing'],
        ], $this->mockArticleVersion([
            'article_id' => 42,
            'language' => Language::EN,
            'status' => ArticleStatus::PUBLISHED,
            'title' => 'Fixture Article',
            'slug' => 'fixture-article',
            'content' => [
                $this->mockParagraphBlock('Fixture body'),
                $this->mockImageBlock('/fixture.png', 'Fixture'),
            ],
        ]));

        self::assertSame(42, $article->getId());
        self::assertSame('Fixture Article', $article->getTitle());
        self::assertSame('fixture-article', $article->getSlug());
        self::assertSame(Language::EN, $article->getLanguage());
        self::assertSame(ArticleStatus::PUBLISHED, $article->getStatus());
        self::assertSame(['cms', 'testing'], $article->getTagNames());
        self::assertSame('/fixture.png', $article->getImage());
    }

    public function testArticleImageRejectsActiveUrlSchemes(): void
    {
        $article = $this->mockArticle([], $this->mockArticleVersion([
            'content' => [$this->mockImageBlock('data:image/svg+xml,<svg onload=alert(1)>')],
        ]));

        self::assertNull($article->getImage());
    }
}
