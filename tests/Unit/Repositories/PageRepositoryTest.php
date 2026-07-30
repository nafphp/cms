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

    public function testSaveRejectsExistingPathLanguagePairInsteadOfOverwritingIt(): void
    {
        $firstId = $this->repository->save([
            'identifier' => 'news',
            'path' => '/news',
            'title' => 'News',
            'language' => 'en',
            'template_id' => 'editorial',
        ]);

        try {
            $this->repository->save([
                'identifier' => 'news-updated',
                'path' => '/news',
                'title' => 'Updated News',
                'language' => 'en',
                'template_id' => 'landing',
            ]);
            self::fail('Expected the duplicate page path to be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('already used', $exception->getMessage());
        }

        $this->assertSame('News', $this->repository->find($firstId)['title']);
        $this->assertSame('editorial', $this->repository->find($firstId)['template_id']);
    }

    public function testFindByPathUsesConfiguredFallbackBeforeAnotherTranslation(): void
    {
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/about',
            'title' => 'Deutsch',
            'language' => 'de',
            'status' => 'published',
            'template_id' => 'default',
        ]);
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/about',
            'title' => 'English',
            'language' => 'en',
            'status' => 'published',
            'template_id' => 'default',
        ]);

        $page = $this->repository->findByPath('/about', 'fr', true, 'en');

        self::assertSame('English', $page['title']);
    }

    public function testRouteDescriptorsContainOnlyOnePublishedRoutePerPath(): void
    {
        $this->repository->save([
            'identifier' => 'draft-only',
            'path' => '/draft-only',
            'title' => 'Draft only',
            'language' => 'en',
            'status' => 'draft',
            'template_id' => 'default',
        ]);
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/about',
            'title' => 'About',
            'language' => 'en',
            'status' => 'published',
            'template_id' => 'default',
        ]);
        $this->repository->save([
            'identifier' => 'about',
            'path' => '/about',
            'title' => 'Über uns',
            'language' => 'de',
            'status' => 'published',
            'template_id' => 'default',
        ]);

        $descriptors = $this->repository->routeDescriptors(['default']);
        $byPath = array_column($descriptors, null, 'path');

        // One route per path, both translations of /about collapsed into one.
        self::assertCount(2, $descriptors);
        self::assertTrue($byPath['/about']['published']);

        // The draft is routed too, otherwise it could not be previewed. It is
        // flagged unpublished, and the page built for it requires PAGES_VIEW.
        self::assertArrayHasKey('/draft-only', $byPath);
        self::assertFalse($byPath['/draft-only']['published']);
    }

    public function testPublishedTranslationDecidesWhetherAPathCountsAsLive(): void
    {
        // The draft sorts first by language, so a naive "first row wins" would
        // report this path as unpublished and let it lose the home override.
        $this->repository->save([
            'identifier' => 'mixed',
            'path' => '/mixed',
            'title' => 'Entwurf',
            'language' => 'de',
            'status' => 'draft',
            'template_id' => 'default',
        ]);
        $this->repository->save([
            'identifier' => 'mixed',
            'path' => '/mixed',
            'title' => 'Published',
            'language' => 'en',
            'status' => 'published',
            'template_id' => 'default',
        ]);

        $descriptors = array_column($this->repository->routeDescriptors(['default']), null, 'path');

        self::assertTrue($descriptors['/mixed']['published']);
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
