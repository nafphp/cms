<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixPHP\CMS\Repositories\PageRepository;
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
                theme_id VARCHAR(120) NULL,
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
            'theme_id' => 'brand',
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
        $this->assertSame('brand', $summary['themeId']);
        $this->assertSame('brand', $this->repository->find($id)['themeId']);
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
            'template_id' => 'default',
        ]);
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/ueber-uns',
            'title' => 'Ueber uns',
            'language' => 'de',
            'template_id' => 'default',
        ]);

        $translations = $this->repository->findTranslations('about');

        $this->assertSame('/ueber-uns', $translations['de']['path']);
        $this->assertSame('/about', $translations['en']['path']);
    }

    public function testCustomPageExamplePersistsCustomLayoutMeta(): void
    {
        $id = $this->repository->save([
            'identifier' => 'custom-example',
            'path' => '/custom-example',
            'title' => 'Custom example',
            'language' => 'en',
            'template_id' => 'custom',
            'blocks' => [
                'slot_hero' => ['type' => 'hero', 'props' => ['title' => 'Custom hero']],
                'slot_text' => ['type' => 'text', 'props' => ['text' => 'Stacked content']],
            ],
            'meta' => [
                'custom_layout' => [
                    ['id' => 'slot_hero', 'label' => 'Hero', 'region' => 'header', 'size' => 'hero', 'accepts' => ['hero']],
                    ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium', 'accepts' => ['text']],
                ],
            ],
        ]);

        $page = $this->repository->find($id);

        self::assertSame('custom', $page['template_id']);
        self::assertSame(['slot_hero', 'slot_text'], array_keys($page['blocks']));
        self::assertSame('header', $page['meta']['custom_layout'][0]['region']);
        self::assertSame(['text'], $page['meta']['custom_layout'][1]['accepts']);
    }

    public function testDeleteRemovesPage(): void
    {
        $id = $this->repository->save([
            'identifier' => 'delete-me',
            'path' => '/delete-me',
            'title' => 'Delete me',
            'language' => 'en',
            'template_id' => 'default',
        ]);

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->find($id));
        $this->assertFalse($this->repository->delete($id));
    }
}
