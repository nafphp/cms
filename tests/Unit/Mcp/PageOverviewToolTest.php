<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Naf\CMS\Mcp\Tools\PageOverviewTool;
use Tests\Support\DatabaseTestCase;

final class PageOverviewToolTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<SQL
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT NOT NULL,
                path TEXT NOT NULL,
                title TEXT NOT NULL,
                language TEXT NOT NULL,
                status TEXT NOT NULL,
                template_id TEXT NOT NULL,
                layout TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )
        SQL);
    }

    public function testReturnsFilteredRecentPagesAndUnfilteredBreakdowns(): void
    {
        $this->insertPage('home', '/', 'Home', 'en', 'published', 'landing', '2026-05-01 09:00:00', '2026-05-03 09:00:00');
        $this->insertPage('news', '/news', 'News', 'en', 'published', 'editorial', '2026-05-02 09:00:00', '2026-05-04 09:00:00');
        $this->insertPage('draft', '/draft', 'Draft', 'de', 'draft', 'editorial', '2026-05-03 09:00:00', null);

        $result = (new PageOverviewTool($this->pdo))->handle([
            'status' => 'PUBLISHED',
            'language' => 'EN',
            'limit' => 1,
        ]);

        self::assertTrue($result['database_available']);
        self::assertSame(2, $result['total']);
        self::assertSame(['draft' => 1, 'published' => 2], $result['status_counts']);
        self::assertSame(['de' => 1, 'en' => 2], $result['language_counts']);
        self::assertSame(['editorial' => 2, 'landing' => 1], $result['template_counts']);
        self::assertCount(1, $result['recent']);
        self::assertSame('News', $result['recent'][0]['title']);
        self::assertSame('/news', $result['recent'][0]['path']);
    }

    public function testCanSkipRecentPagesForSmallCapabilityPayloads(): void
    {
        $this->insertPage('home', '/', 'Home', 'en', 'published', 'landing', '2026-05-01 09:00:00', null);

        $result = (new PageOverviewTool($this->pdo))->handle(['include_recent' => false]);

        self::assertTrue($result['database_available']);
        self::assertSame(1, $result['total']);
        self::assertSame([], $result['recent']);
    }

    private function insertPage(
        string $identifier,
        string $path,
        string $title,
        string $language,
        string $status,
        string $templateId,
        string $createdAt,
        ?string $updatedAt,
    ): void {
        $this->insertRow('pages', [
            'identifier' => $identifier,
            'path' => $path,
            'title' => $title,
            'language' => $language,
            'status' => $status,
            'template_id' => $templateId,
            'layout' => 'layouts.page',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
