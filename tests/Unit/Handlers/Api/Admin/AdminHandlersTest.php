<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Admin;

use Naf\CMS\Handlers\Api\Admin\GetSummary;
use Tests\Support\DatabaseTestCase;

final class AdminHandlersTest extends DatabaseTestCase
{
    public function testSummaryReturnsTheDashboardJsonBoundary(): void
    {
        $this->createSchema(
            'CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, author INTEGER NULL)',
            'CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL
            )',
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
        );
        $response = (new GetSummary())();
        $payload  = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($payload);
        foreach (['articles', 'published_articles', 'review_queue', 'users', 'charts', 'platform_stats'] as $key) {
            self::assertArrayHasKey($key, $payload);
        }
    }
}
