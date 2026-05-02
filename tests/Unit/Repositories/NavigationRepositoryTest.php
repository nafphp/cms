<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixCMS\Repositories\NavigationRepository;
use Tests\Support\DatabaseTestCase;

final class NavigationRepositoryTest extends DatabaseTestCase
{
    private NavigationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<SQL
            CREATE TABLE navigations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT NOT NULL,
                title TEXT NOT NULL,
                scope TEXT NOT NULL DEFAULT 'frontend',
                language TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'draft',
                items_json TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NULL,
                UNIQUE (identifier, scope, language)
            )
        SQL);

        $this->repository = new NavigationRepository($this->pdo);
    }

    public function testSaveAndFindRoundTripsNestedNavigation(): void
    {
        $id = $this->repository->save([
            'identifier' => 'main',
            'title' => 'Main navigation',
            'status' => 'published',
            'items' => [
                [
                    'key' => 'home',
                    'label' => 'Home',
                    'href' => '/',
                    'children' => [
                        ['key' => 'docs', 'label' => 'Docs', 'href' => '/docs'],
                    ],
                ],
            ],
        ]);

        $navigation = $this->repository->find($id);

        self::assertNotNull($navigation);
        self::assertSame('main', $navigation->identifier());
        self::assertSame('Main navigation', $navigation->title());
        self::assertSame('published', $navigation->status());
        self::assertSame('docs', $navigation->toArray()['items'][0]['children'][0]['key']);
    }

    public function testFindByIdentifierFallsBackToDefaultLanguage(): void
    {
        $this->repository->save([
            'identifier' => 'footer',
            'title' => 'Footer',
            'items' => [],
        ]);

        self::assertSame('Footer', $this->repository->findByIdentifier('footer', language: 'de')?->title());
    }

    public function testSaveUpdatesExistingIdentifierLanguagePairAsDraft(): void
    {
        $id = $this->repository->save([
            'identifier' => 'main',
            'title' => 'Main',
            'language' => 'de',
            'status' => 'published',
            'items' => [],
        ]);

        $updatedId = $this->repository->save([
            'identifier' => 'main',
            'title' => 'Main updated',
            'language' => 'de',
            'status' => 'draft',
            'items' => [],
        ]);

        self::assertSame($id, $updatedId);
        self::assertSame('Main updated', $this->repository->find($id)?->title());
        self::assertSame('draft', $this->repository->find($id)?->status());
    }

    public function testPublishChangesStatus(): void
    {
        $id = $this->repository->save([
            'identifier' => 'main',
            'title' => 'Main',
            'language' => 'en',
            'items' => [],
        ]);

        self::assertTrue($this->repository->publish($id));
        self::assertSame('published', $this->repository->find($id)?->status());
    }

    public function testDeleteRemovesNavigation(): void
    {
        $id = $this->repository->save([
            'identifier' => 'main',
            'title' => 'Main',
            'language' => 'en',
            'items' => [],
        ]);

        self::assertTrue($this->repository->delete($id));
        self::assertNull($this->repository->find($id));
        self::assertFalse($this->repository->delete($id));
    }
}
