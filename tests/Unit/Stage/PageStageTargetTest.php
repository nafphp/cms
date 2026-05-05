<?php

declare(strict_types=1);

namespace Tests\Unit\Stage;

use NixCMS\Content\Page\Block\Types\TextBlockType;
use NixCMS\Repositories\PageRepository;
use NixCMS\Repositories\StageRepository;
use NixCMS\Stage\Stage;
use NixCMS\Stage\Targets\PageStageTarget;
use Tests\Support\DatabaseTestCase;

final class PageStageTargetTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            <<<'SQL'
                CREATE TABLE pages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    identifier TEXT NOT NULL,
                    path TEXT NOT NULL,
                    title TEXT NOT NULL,
                    language TEXT NOT NULL DEFAULT '',
                    status TEXT NOT NULL DEFAULT 'draft',
                    template_id TEXT NOT NULL,
                    theme_id TEXT NULL,
                    blocks_json TEXT NOT NULL,
                    meta_json TEXT NOT NULL,
                    layout TEXT NOT NULL DEFAULT 'layouts.page',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL
                )
            SQL,
            <<<'SQL'
                CREATE TABLE cms_stages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    target_type TEXT NOT NULL,
                    target_id TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'open',
                    base_snapshot_json TEXT NOT NULL,
                    working_snapshot_json TEXT NOT NULL,
                    changes_json TEXT NOT NULL,
                    ui_actions_json TEXT NOT NULL,
                    warnings_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL,
                    committed_at TEXT NULL
                )
            SQL,
        );

        \cms()->blockTypes()->add(new TextBlockType(), true);
    }

    public function testPageBlockAddIsStagedAndCommittedWithoutPublishing(): void
    {
        $pageId = $this->insertRow('pages', [
            'identifier' => 'home',
            'path' => '/',
            'title' => 'Home',
            'language' => 'en',
            'status' => 'draft',
            'template_id' => 'custom',
            'theme_id' => 'default',
            'blocks_json' => '{}',
            'meta_json' => '{"custom_layout":[]}',
            'layout' => 'layouts.page',
        ]);

        $pages = new PageRepository($this->pdo);
        $target = new PageStageTarget($pages->find($pageId) ?? [], $pages);
        $stage = Stage::create($target, new StageRepository($this->pdo));

        $stage->change('block.add', [
            'region' => 'content',
            'blockType' => 'text',
            'config' => ['text' => 'Blabla'],
        ]);

        self::assertNotNull($stage->id());
        self::assertSame('open', $stage->status());
        self::assertSame('pagebuilder.addBlock', $stage->uiActions()[0]['action']);

        $result = $stage->commit();

        self::assertTrue($result->successful());
        self::assertSame('committed', $stage->status());

        $row = $this->fetchOne('SELECT status, blocks_json, meta_json FROM pages WHERE id = :id', ['id' => $pageId]);
        self::assertSame('draft', $row['status']);

        $blocks = json_decode((string) $row['blocks_json'], true, 512, JSON_THROW_ON_ERROR);
        $meta = json_decode((string) $row['meta_json'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('text', $blocks['ai_text']['type']);
        self::assertSame('Blabla', $blocks['ai_text']['props']['text']);
        self::assertSame('main', $meta['custom_layout'][0]['region']);
    }
}
