<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Naf\CMS\Mcp\Tools\ContentOverviewTool;
use Tests\Support\DatabaseTestCase;

final class ContentOverviewToolTest extends DatabaseTestCase
{
    public function testAggregatesContentCountsFromAvailableTablesAndLatestArticleVersions(): void
    {
        $this->createSchema(
            'CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL
            )',
            'CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                language TEXT NOT NULL,
                status TEXT NOT NULL
            )',
            'CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT)',
        );

        $this->insertRow('articles', ['id' => null]);
        $this->insertRow('articles', ['id' => null]);
        $this->insertVersion(1, 'en', 1, 'draft');
        $this->insertVersion(1, 'en', 2, 'published');
        $this->insertVersion(1, 'de', 1, 'draft');
        $this->insertVersion(2, 'en', 1, 'review');
        $this->insertVersion(2, 'en', 2, 'declined');
        $this->insertRow('categories', ['id' => null]);
        $this->insertRow('tags', ['id' => null]);
        $this->insertRow('tags', ['id' => null]);
        $this->insertRow('pages', ['language' => 'en', 'status' => 'published']);
        $this->insertRow('pages', ['language' => 'en', 'status' => 'published']);
        $this->insertRow('pages', ['language' => '', 'status' => 'draft']);
        $this->insertRow('media', ['id' => null]);
        $this->insertRow('users', ['id' => null]);
        $this->insertRow('audit_log', ['id' => null]);

        $result = (new ContentOverviewTool($this->pdo))->handle([]);

        self::assertTrue($result['database_available']);
        self::assertSame(2, $result['counts']['articles']);
        self::assertSame(5, $result['counts']['article_versions']);
        self::assertSame(3, $result['counts']['latest_article_versions']);
        self::assertSame(2, $result['counts']['tags']);
        self::assertSame(3, $result['counts']['pages']);
        self::assertSame(['declined' => 1, 'draft' => 1, 'published' => 1], $result['article_status_counts']);
        self::assertSame(['draft' => 1, 'published' => 2], $result['page_status_counts']);
        self::assertSame(['de' => 1, 'en' => 4], $result['language_counts']['articles']);
        self::assertSame(['default' => 1, 'en' => 2], $result['language_counts']['pages']);
    }

    public function testMissingDatabaseTablesReturnZeroedCountsInsteadOfErrors(): void
    {
        $result = (new ContentOverviewTool($this->pdo))->handle([]);

        self::assertTrue($result['database_available']);
        self::assertSame(0, $result['counts']['articles']);
        self::assertSame(0, $result['counts']['latest_article_versions']);
        self::assertSame([], $result['article_status_counts']);
        self::assertSame([], $result['page_status_counts']);
    }

    private function insertVersion(int $articleId, string $language, int $version, string $status): void
    {
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language'   => $language,
            'version'    => $version,
            'status'     => $status,
        ]);
    }
}
