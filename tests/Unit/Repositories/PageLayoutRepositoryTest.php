<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixPHP\CMS\Repositories\PageLayoutRepository;
use Tests\Support\DatabaseTestCase;

final class PageLayoutRepositoryTest extends DatabaseTestCase
{
    private PageLayoutRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<SQL
            CREATE TABLE page_layouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier VARCHAR(160) NOT NULL UNIQUE,
                name VARCHAR(190) NOT NULL,
                description TEXT NULL,
                layout VARCHAR(120) NOT NULL DEFAULT 'layouts.page',
                view VARCHAR(160) NOT NULL DEFAULT 'page.templates.custom',
                slots_json TEXT NOT NULL,
                meta_json TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            )
        SQL);
        $this->createSchema(<<<SQL
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                meta_json TEXT NOT NULL
            )
        SQL);

        $this->repository = new PageLayoutRepository($this->pdo);
    }

    public function testSaveCreatesReusableTemplateDefinition(): void
    {
        $layout = $this->repository->save([
            'name' => 'Campaign layout',
            'description' => 'Reusable campaign structure.',
            'slots' => [
                [
                    'id' => 'slot_hero',
                    'label' => 'Hero',
                    'region' => 'header',
                    'size' => 'hero',
                    'accepts' => ['hero'],
                    'defaultContentType' => 'hero',
                ],
            ],
        ]);

        self::assertGreaterThan(0, $layout['id']);
        self::assertSame('campaign-layout', $layout['identifier']);
        self::assertSame('slot_hero', $layout['slots'][0]['id']);

        $template = $this->repository->templates()[0];

        self::assertSame('custom_layout.' . $layout['id'], $template['id']);
        self::assertSame('custom', $template['baseTemplateId']);
        self::assertSame('custom', $template['kind']);
        self::assertSame('Campaign layout', $template['label']);
        self::assertSame('slot_hero', $template['blocks'][0]['id']);
    }

    public function testSaveMakesIdentifiersUnique(): void
    {
        $first = $this->repository->save([
            'name' => 'Campaign layout',
            'slots' => [
                ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ],
        ]);
        $second = $this->repository->save([
            'name' => 'Campaign layout',
            'slots' => [
                ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ],
        ]);

        self::assertSame('campaign-layout', $first['identifier']);
        self::assertSame('campaign-layout-2', $second['identifier']);
    }

    public function testDeleteRemovesLayout(): void
    {
        $layout = $this->repository->save([
            'name' => 'Temporary layout',
            'slots' => [
                ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ],
        ]);

        self::assertTrue($this->repository->delete((int) $layout['id']));
        self::assertNull($this->repository->find((int) $layout['id']));
    }

    public function testDeleteRefusesWhenReferencedByPage(): void
    {
        $layout = $this->repository->save([
            'name' => 'Referenced layout',
            'slots' => [
                ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ],
        ]);

        $this->insertRow('pages', [
            'meta_json' => json_encode(['layoutPresetId' => $layout['id']], JSON_THROW_ON_ERROR),
        ]);

        self::assertFalse($this->repository->delete((int) $layout['id']));
        self::assertSame('Layout is still used by one or more pages.', $this->repository->lastError());
        self::assertNotNull($this->repository->find((int) $layout['id']));
    }

    public function testDeleteRefusesWhenReferencedByCurrentPageBuilderMeta(): void
    {
        $layout = $this->repository->save([
            'name' => 'Current reference layout',
            'slots' => [
                ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ],
        ]);

        $this->insertRow('pages', [
            'meta_json' => json_encode(['custom_layout_ref' => $layout['id']], JSON_THROW_ON_ERROR),
        ]);

        self::assertFalse($this->repository->delete((int) $layout['id']));
        self::assertSame('Layout is still used by one or more pages.', $this->repository->lastError());
        self::assertNotNull($this->repository->find((int) $layout['id']));
    }

    public function testSaveFiltersMeta(): void
    {
        $layout = $this->repository->save([
            'name' => 'Meta layout',
            'slots' => [
                ['id' => 'slot_text', 'label' => 'Text', 'region' => 'main', 'size' => 'medium'],
            ],
            'meta' => [
                'source' => 'page-builder',
                'createdBy' => 42,
                'tags' => ['landing', '', ['nested'], 'landing', 'campaign'],
                'unexpected' => 'drop-me',
            ],
        ]);

        self::assertSame([
            'source' => 'page-builder',
            'createdBy' => '42',
            'tags' => ['landing', 'campaign'],
        ], $layout['meta']);
    }
}
