<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use NixPHP\CMS\Mcp\Tools\ArticleLookupTool;
use Tests\Support\DatabaseTestCase;

final class ArticleLookupToolTest extends DatabaseTestCase
{
    public function testFindsLatestArticleVersionsByTitle(): void
    {
        $this->createSchema(
            'CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NULL, createdAt TEXT NULL)',
            'CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL)',
            'CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL,
                slug TEXT NOT NULL,
                title TEXT NOT NULL,
                teaser TEXT NULL,
                createdAt TEXT NOT NULL
            )'
        );

        $categoryId = $this->insertRow('categories', ['name' => 'Intern', 'slug' => 'intern']);
        $articleId = $this->insertRow('articles', ['category_id' => $categoryId, 'createdAt' => '2026-05-03 10:00:00']);
        $this->insertVersion($articleId, 'de', 1, 'draft', 'Old title', 'old-title', '2026-05-03 10:01:00');
        $this->insertVersion($articleId, 'de', 2, 'draft', 'Das ist ein Test.', 'das-ist-ein-test', '2026-05-03 10:02:00');

        $result = (new ArticleLookupTool($this->pdo))->handle([
            'query' => 'test',
            'language' => 'de',
            'status' => 'draft',
        ]);

        self::assertTrue($result['database_available']);
        self::assertCount(1, $result['matches']);
        self::assertSame($articleId, $result['matches'][0]['articleId']);
        self::assertSame(2, $result['matches'][0]['version']);
        self::assertSame('Intern', $result['matches'][0]['category']);
    }

    private function insertVersion(
        int $articleId,
        string $language,
        int $version,
        string $status,
        string $title,
        string $slug,
        string $createdAt,
    ): int {
        return $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => $language,
            'version' => $version,
            'status' => $status,
            'slug' => $slug,
            'title' => $title,
            'teaser' => '',
            'createdAt' => $createdAt,
        ]);
    }
}
