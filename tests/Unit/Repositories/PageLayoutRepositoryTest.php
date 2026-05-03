<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixCMS\Repositories\PageLayoutRepository;
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
}
