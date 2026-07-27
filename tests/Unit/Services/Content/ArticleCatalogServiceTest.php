<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Content;

use src\Services\Content\ArticleCatalogService;
use Tests\Support\DatabaseTestCase;

final class ArticleCatalogServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL, firstname TEXT, lastname TEXT)',
            'CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, author INTEGER, createdAt TEXT NOT NULL)',
            'CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL,
                slug TEXT NOT NULL,
                title TEXT NOT NULL,
                publishedAt TEXT,
                scheduledAt TEXT,
                createdAt TEXT NOT NULL
            )',
        );
    }

    public function testOverviewShapesArticlesAndPrefersPublishedSummary(): void
    {
        $authorId = $this->insertRow('users', [
            'username' => 'flo',
            'firstname' => 'Flo',
            'lastname' => 'Knapp',
        ]);
        $articleId = $this->insertRow('articles', [
            'author' => $authorId,
            'createdAt' => '2026-05-01 10:00:00',
        ]);

        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'de',
            'version' => 1,
            'status' => 'published',
            'slug' => 'published-title',
            'title' => 'Published title',
            'publishedAt' => '2026-05-01 11:00:00',
            'scheduledAt' => null,
            'createdAt' => '2026-05-01 10:30:00',
        ]);
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'de',
            'version' => 2,
            'status' => 'draft',
            'slug' => 'draft-title',
            'title' => 'Draft title',
            'publishedAt' => null,
            'scheduledAt' => null,
            'createdAt' => '2026-05-01 12:00:00',
        ]);

        $overview = (new ArticleCatalogService($this->pdo))->overview();

        self::assertContains('published', $overview['statuses']);
        self::assertContains('de', $overview['languages']);
        self::assertCount(1, $overview['articles']);
        self::assertSame('Published title', $overview['articles'][0]['title']);
        self::assertSame('published-title', $overview['articles'][0]['slug']);
        self::assertSame('published', $overview['articles'][0]['status']);
        self::assertSame('Flo Knapp', $overview['articles'][0]['author']);
        self::assertCount(2, $overview['articles'][0]['versions']);
    }

    public function testArticlesFallsBackToUsernameAndLatestDraftWhenNothingPublished(): void
    {
        $authorId = $this->insertRow('users', [
            'username' => 'editor',
            'firstname' => '',
            'lastname' => '',
        ]);
        $articleId = $this->insertRow('articles', [
            'author' => $authorId,
            'createdAt' => '2026-05-01 10:00:00',
        ]);

        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'en',
            'version' => 1,
            'status' => 'draft',
            'slug' => 'first-draft',
            'title' => 'First draft',
            'publishedAt' => null,
            'scheduledAt' => null,
            'createdAt' => '2026-05-01 10:30:00',
        ]);
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'en',
            'version' => 2,
            'status' => 'review',
            'slug' => 'review-draft',
            'title' => 'Review draft',
            'publishedAt' => null,
            'scheduledAt' => null,
            'createdAt' => '2026-05-01 11:30:00',
        ]);

        $articles = (new ArticleCatalogService($this->pdo))->articles();

        self::assertSame('Review draft', $articles[0]['title']);
        self::assertSame('review', $articles[0]['status']);
        self::assertSame('editor', $articles[0]['author']);
    }
}
