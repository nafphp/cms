<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use NixCMS\Mcp\Tools\ArticleStatusCountsTool;
use Tests\Support\DatabaseTestCase;

final class ArticleStatusCountsToolTest extends DatabaseTestCase
{
    public function testCountsLatestVersionsForAllLanguages(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL
            )
        SQL);

        $this->insertVersion(1, 'en', 1, 'draft');
        $this->insertVersion(1, 'en', 2, 'published');
        $this->insertVersion(1, 'de', 1, 'draft');
        $this->insertVersion(2, 'en', 1, 'review');
        $this->insertVersion(2, 'de', 1, 'review');
        $this->insertVersion(2, 'de', 2, 'declined');

        $result = (new ArticleStatusCountsTool($this->pdo))->handle([]);

        self::assertTrue($result['database_available']);
        self::assertSame('all', $result['language']);
        self::assertSame(4, $result['total']);
        self::assertSame(1, $result['counts']['draft']);
        self::assertSame(1, $result['counts']['review']);
        self::assertSame(1, $result['counts']['declined']);
        self::assertSame(1, $result['counts']['published']);
        self::assertSame(0, $result['counts']['scheduled']);
        self::assertSame(0, $result['counts']['archived']);
    }

    public function testCountsLatestVersionsForOneLanguage(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL
            )
        SQL);

        $this->insertVersion(1, 'en', 1, 'draft');
        $this->insertVersion(1, 'en', 2, 'published');
        $this->insertVersion(1, 'de', 1, 'draft');
        $this->insertVersion(2, 'en', 1, 'review');
        $this->insertVersion(2, 'de', 1, 'declined');

        $result = (new ArticleStatusCountsTool($this->pdo))->handle(['language' => 'EN']);

        self::assertSame('en', $result['language']);
        self::assertSame(2, $result['total']);
        self::assertSame(1, $result['counts']['published']);
        self::assertSame(1, $result['counts']['review']);
        self::assertSame(0, $result['counts']['draft']);
    }

    public function testReturnsEmptyCountsWhenSchemaIsMissing(): void
    {
        $result = (new ArticleStatusCountsTool($this->pdo))->handle([]);

        self::assertFalse($result['database_available']);
        self::assertSame(0, $result['total']);
        self::assertSame([
            'draft' => 0,
            'review' => 0,
            'declined' => 0,
            'published' => 0,
            'scheduled' => 0,
            'archived' => 0,
        ], $result['counts']);
    }

    private function insertVersion(int $articleId, string $language, int $version, string $status): void
    {
        $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => $language,
            'version' => $version,
            'status' => $status,
        ]);
    }
}
