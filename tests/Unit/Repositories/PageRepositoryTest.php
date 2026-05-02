<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixCMS\Repositories\PageRepository;
use Tests\Support\DatabaseTestCase;

final class PageRepositoryTest extends DatabaseTestCase
{
    private PageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<SQL
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier VARCHAR(160) NOT NULL,
                path VARCHAR(255) NOT NULL,
                title VARCHAR(190) NOT NULL,
                language VARCHAR(8) NOT NULL DEFAULT '',
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                template_id VARCHAR(120) NOT NULL,
                blocks_json TEXT NOT NULL,
                meta_json TEXT NOT NULL,
                layout VARCHAR(120) NOT NULL DEFAULT 'layouts.page',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE (path, language)
            )
        SQL);

        $this->repository = new PageRepository($this->pdo);
    }

    public function testSaveCreatesAndSummarizesPage(): void
    {
        $id = $this->repository->save([
            'identifier' => 'news',
            'path' => '/news',
            'title' => 'News',
            'language' => 'en',
            'status' => 'draft',
            'template_id' => 'editorial',
            'layout' => 'layouts.page',
            'blocks' => ['intro' => ['type' => 'heading', 'props' => ['text' => 'Latest']]],
            'meta' => ['description' => 'Overview'],
        ]);

        $this->assertGreaterThan(0, $id);

        $summary = $this->repository->summaries([
            ['id' => 'editorial', 'label' => 'Editorial index'],
        ])[0];

        $this->assertSame('News', $summary['title']);
        $this->assertSame('/news', $summary['path']);
        $this->assertSame('Editorial index', $summary['template']);
    }

    public function testSaveUpdatesExistingPathLanguagePair(): void
    {
        $firstId = $this->repository->save([
            'identifier' => 'news',
            'path' => '/news',
            'title' => 'News',
            'language' => 'en',
            'template_id' => 'editorial',
        ]);

        $secondId = $this->repository->save([
            'identifier' => 'news-updated',
            'path' => '/news',
            'title' => 'Updated News',
            'language' => 'en',
            'template_id' => 'landing',
        ]);

        $this->assertSame($firstId, $secondId);
        $this->assertSame('Updated News', $this->repository->find($firstId)['title']);
    }

    public function testFindTranslationsReturnsRowsByLanguage(): void
    {
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/about',
            'title' => 'About',
            'language' => 'en',
            'template_id' => 'public',
        ]);
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/ueber-uns',
            'title' => 'Ueber uns',
            'language' => 'de',
            'template_id' => 'public',
        ]);

        $translations = $this->repository->findTranslations('about');

        $this->assertSame('/ueber-uns', $translations['de']['path']);
        $this->assertSame('/about', $translations['en']['path']);
    }

    public function testDeleteRemovesPage(): void
    {
        $id = $this->repository->save([
            'identifier' => 'delete-me',
            'path' => '/delete-me',
            'title' => 'Delete me',
            'language' => 'en',
            'template_id' => 'public',
        ]);

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->find($id));
        $this->assertFalse($this->repository->delete($id));
    }
}
