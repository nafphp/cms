<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Content;

use NixCMS\Services\Content\ArticleFormService;
use Tests\Support\DatabaseTestCase;

final class ArticleFormServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            'CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL, firstname TEXT, lastname TEXT)',
            'CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                author INTEGER,
                hide_lead_image_in_content INTEGER NOT NULL DEFAULT 0
            )',
            'CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL,
                slug TEXT NOT NULL,
                title TEXT NOT NULL,
                teaser TEXT,
                content TEXT,
                publishedAt TEXT,
                show_teaser INTEGER NOT NULL DEFAULT 1
            )',
            'CREATE TABLE article_tags (article_id INTEGER NOT NULL, tag_id INTEGER NOT NULL)',
            'CREATE TABLE article_review_actions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_version_id INTEGER NOT NULL,
                reviewed_by INTEGER,
                action TEXT NOT NULL,
                comment TEXT,
                reviewed_at TEXT NOT NULL
            )',
        );
    }

    public function testFindArticleForFormReturnsSelectedVersionWithTags(): void
    {
        $categoryId = $this->insertRow('categories', ['name' => 'CMS']);
        $articleId = $this->insertRow('articles', [
            'category_id' => $categoryId,
            'author' => 7,
            'hide_lead_image_in_content' => 1,
        ]);
        $tagId = $this->insertRow('tags', ['name' => 'Architecture']);
        $this->insertRow('article_tags', ['article_id' => $articleId, 'tag_id' => $tagId]);
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'de',
            'version' => 1,
            'status' => 'draft',
            'slug' => 'alter-titel',
            'title' => 'Alter Titel',
            'teaser' => '',
            'content' => '{}',
            'publishedAt' => null,
            'show_teaser' => 1,
        ]);
        $versionId = $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'de',
            'version' => 2,
            'status' => 'review',
            'slug' => 'neuer-titel',
            'title' => 'Neuer Titel',
            'teaser' => 'Kurz',
            'content' => '{"blocks":[]}',
            'publishedAt' => null,
            'show_teaser' => 1,
        ]);

        $article = (new ArticleFormService($this->pdo))->findArticleForForm($articleId, 'de');

        self::assertNotNull($article);
        self::assertSame($versionId, (int) $article['article_version_id']);
        self::assertSame('CMS', $article['category_name']);
        self::assertSame('Neuer Titel', $article['title']);
        self::assertSame(['Architecture'], $article['tags']);
    }

    public function testNextVersionIsLanguageAware(): void
    {
        $articleId = $this->insertRow('articles', [
            'category_id' => null,
            'author' => 1,
            'hide_lead_image_in_content' => 0,
        ]);
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'de',
            'version' => 3,
            'status' => 'draft',
            'slug' => 'de',
            'title' => 'DE',
            'teaser' => '',
            'content' => '{}',
            'publishedAt' => null,
            'show_teaser' => 1,
        ]);
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'en',
            'version' => 1,
            'status' => 'draft',
            'slug' => 'en',
            'title' => 'EN',
            'teaser' => '',
            'content' => '{}',
            'publishedAt' => null,
            'show_teaser' => 1,
        ]);

        $service = new ArticleFormService($this->pdo);

        self::assertSame(4, $service->nextVersion($articleId, 'DE'));
        self::assertSame(2, $service->nextVersion($articleId, 'en'));
        self::assertSame(1, $service->nextVersion($articleId, 'fr'));
    }

    public function testReviewActionsAreNewestFirstAndIncludeReviewerNames(): void
    {
        $reviewerId = $this->insertRow('users', [
            'username' => 'reviewer',
            'firstname' => 'Ria',
            'lastname' => 'Review',
        ]);
        $articleId = $this->insertRow('articles', [
            'category_id' => null,
            'author' => $reviewerId,
            'hide_lead_image_in_content' => 0,
        ]);
        $versionId = $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => 'de',
            'version' => 1,
            'status' => 'review',
            'slug' => 'review',
            'title' => 'Review',
            'teaser' => '',
            'content' => '{}',
            'publishedAt' => null,
            'show_teaser' => 1,
        ]);

        $this->insertRow('article_review_actions', [
            'article_version_id' => $versionId,
            'reviewed_by' => $reviewerId,
            'action' => 'declined',
            'comment' => 'Bitte schärfen',
            'reviewed_at' => '2026-05-01 10:00:00',
        ]);
        $this->insertRow('article_review_actions', [
            'article_version_id' => $versionId,
            'reviewed_by' => $reviewerId,
            'action' => 'approved',
            'comment' => 'Passt',
            'reviewed_at' => '2026-05-01 11:00:00',
        ]);

        $actions = (new ArticleFormService($this->pdo))->reviewActions($versionId);

        self::assertSame('approved', $actions[0]['action']);
        self::assertSame('Ria', $actions[0]['firstname']);
        self::assertSame('declined', $actions[1]['action']);
    }
}
