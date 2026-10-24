<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Docs;

use Naf\CMS\Page\Page;
use Naf\CMS\Support\Cms;
use Naf\CMS\Support\Docs\PagesDocRenderer;
use Tests\Support\DatabaseTestCase;

use function Naf\CMS\cms;

final class PagesDocRendererTest extends DatabaseTestCase
{
    public function testDocumentationRetainsCodeDefinitionsAndExcludesStoredPages(): void
    {
        $this->createSchema(<<<'SQL'
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL, path TEXT NOT NULL,
                title TEXT NOT NULL, language TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft',
                template_id TEXT NOT NULL, theme_id TEXT NULL, blocks_json TEXT NOT NULL, meta_json TEXT NOT NULL,
                layout TEXT NOT NULL DEFAULT 'layouts.page', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NULL
            )
        SQL);
        foreach (['/reference', '/installation-only'] as $path) {
            $this->insertRow('pages', ['identifier' => $path, 'path' => $path, 'title' => 'Installation page', 'status' => 'published', 'template_id' => 'default', 'blocks_json' => '{}', 'meta_json' => '{}']);
        }
        $previous = cms();
        cms(override: new Cms());

        try {
            $registry   = cms()->pages();
            $definition = Page::create('code.reference')->setPath('/reference')->allowStoredOverride();
            $registry->add($definition);
            $renderer = new PagesDocRenderer();
            $before   = $renderer->render();
            self::assertArrayHasKey('db.page.1', $registry->all());
            self::assertNull($registry->get('code.reference'), 'the stored page overrides the runtime route');
            self::assertSame(['code.reference' => $definition], $registry->registered());
            self::assertSame($before, $renderer->render(), 'materializing stored pages does not change generated documentation');
            self::assertStringContainsString('code.reference', $before);
            self::assertStringNotContainsString('db.page.', $before);
            self::assertStringNotContainsString('/installation-only', $before);
        } finally {
            cms(override: $previous);
        }
    }
}
