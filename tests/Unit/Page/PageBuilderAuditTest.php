<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CMS\Commands\PageBuilderAuditCommand;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\PageBuilderAudit;
use Naf\CMS\Repositories\PageRepository;
use Naf\CMS\Support\Cms;
use stdClass;
use Tests\Support\CoreBlockTypes;
use Tests\Support\DatabaseTestCase;

use function Naf\CMS\cms;

final class PageBuilderAuditTest extends DatabaseTestCase
{
    private Cms $previousCms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema(<<<'SQL'
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL, path TEXT NOT NULL,
                title TEXT NOT NULL, language TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft',
                template_id TEXT NOT NULL, theme_id TEXT NULL, blocks_json TEXT NOT NULL, meta_json TEXT NOT NULL,
                layout TEXT NOT NULL DEFAULT 'layouts.page', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NULL
            )
        SQL);
        $this->previousCms = cms();
        $fresh             = new Cms();
        $fresh->blockTypes()->addMany(CoreBlockTypes::all());
        cms(override: $fresh);
    }

    protected function tearDown(): void
    {
        cms(override: $this->previousCms);
        parent::tearDown();
    }

    public function testListsOnlyPagesThatBreakTheCurrentRules(): void
    {
        $clean    = $this->page('/clean', 'default', ['main' => ['type' => 'text', 'props' => ['text' => 'Fine']]]);
        $disabled = $this->page('/disabled', 'default', ['main' => ['type' => 'quote', 'props' => ['quote' => 'Old']]]);
        $custom   = $this->page('/custom', 'custom', ['slot_header' => ['type' => 'text', 'props' => []]], [
            'custom_layout' => [['id' => 'slot_header', 'label' => 'Header', 'region' => 'header']],
        ]);
        $unknown = $this->page('/unknown', 'removed-template', []);
        cms()->blockTypes()->disable('quote')->restrict('text', Permission::THEMES_CODE_MANAGE);

        $findings = array_column($this->audit()->run(), null, 'id');

        self::assertArrayNotHasKey($clean, $findings, 'permissions depend on the editor, not the page');
        self::assertSame(
            ['blocks.main uses block type "quote", which is not available in the page builder.'],
            $findings[$disabled]['problems'],
        );
        self::assertSame('/disabled', $findings[$disabled]['path']);
        self::assertSame(
            ['blocks.slot_header cannot use block type "text" in the header area of a custom layout.'],
            $findings[$custom]['problems'],
        );
        self::assertSame(['Uses unknown page template "removed-template".'], $findings[$unknown]['problems']);
    }

    public function testTheCommandReportsFindingsAndFailsOnlyWhenThereAreAny(): void
    {
        $this->page('/clean', 'default', ['main' => ['type' => 'text', 'props' => ['text' => 'Fine']]]);
        [$status, $output] = $this->runCommand();
        self::assertSame(0, $status);
        self::assertStringContainsString('All stored pages follow the current page-builder rules.', $output);

        $id = $this->page('/disabled', 'default', ['main' => ['type' => 'quote', 'props' => []]]);
        cms()->blockTypes()->disable('quote');
        [$status, $output] = $this->runCommand();
        self::assertSame(1, $status);
        self::assertStringContainsString('1 page(s) break the current page-builder rules:', $output);
        self::assertStringContainsString('#' . $id . ' /disabled [en] "Page"', $output);
        self::assertStringContainsString('- blocks.main uses block type "quote"', $output);
    }

    private function audit(): PageBuilderAudit
    {
        return new PageBuilderAudit(new PageRepository($this->pdo), null);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(): array
    {
        $command = new PageBuilderAuditCommand($this->audit());
        ob_start();

        try {
            $status = $command->run(new Input([], $command->getDefinition()), new Output());
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$status, $output];
    }

    /**
     * @param array<string, mixed> $blocks
     * @param array<string, mixed> $meta
     */
    private function page(string $path, string $template, array $blocks, array $meta = []): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO pages (identifier, path, title, language, template_id, blocks_json, meta_json) VALUES (:identifier, :path, :title, :language, :template, :blocks, :meta)',
        );
        $statement->execute([
            'identifier' => trim($path, '/'),
            'path'       => $path,
            'title'      => 'Page',
            'language'   => 'en',
            'template'   => $template,
            'blocks'     => json_encode($blocks === [] ? new stdClass() : $blocks, JSON_THROW_ON_ERROR),
            'meta'       => json_encode($meta === [] ? new stdClass() : $meta, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
