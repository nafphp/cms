<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Page\Pages\StorePage;
use Naf\CMS\Page\PageBuilderPayloadNormalizer;
use Naf\CMS\Support\Cms;
use Naf\CMS\Support\UserHelper;
use Tests\Support\CoreBlockTypes;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

use function Naf\CMS\cms;
use function Naf\CMS\user;

/**
 * The page-builder rules at the boundary that enforces them: saving a page.
 */
final class StorePagePlacementTest extends DatabaseTestCase
{
    private Cms $previousCms;
    private ?UserHelper $previousUser;

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

        $this->previousCms  = cms();
        $this->previousUser = user();
        $fresh              = new Cms();
        $fresh->blockTypes()->addMany(CoreBlockTypes::all());
        cms(override: $fresh);
        $this->actAs(Permission::PAGES_CREATE, Permission::PAGES_EDIT);
    }

    protected function tearDown(): void
    {
        cms(override: $this->previousCms);
        user($this->previousUser, reset: $this->previousUser === null);
        parent::tearDown();
    }

    public function testANewBlockOfADisabledTypeIsRejected(): void
    {
        cms()->blockTypes()->disable('quote');

        [$status, $payload] = $this->store(['main' => $this->quote('New')]);

        self::assertSame(400, $status);
        self::assertContains(
            'blocks.main uses block type "quote", which is not available in the page builder.',
            $payload['errors']['blocks'] ?? [],
        );
    }

    public function testAPageWithAnOlderBlockStaysEditableAfterTheTypeWasDisabled(): void
    {
        $id = $this->storedPage(['main' => $this->quote('Old')]);
        cms()->blockTypes()->only('text', 'heading', 'header', 'footer');

        [$status, $payload] = $this->store(['main' => $this->quote('Edited')], id: $id, title: 'Renamed');

        self::assertSame(200, $status, json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertSame('Edited', cms()->pages()->find($id)['blocks']['main']['props']['quote'] ?? null);
    }

    public function testRestrictedBlocksNeedThePermissionButCanBeKeptUnchanged(): void
    {
        $embed = ['type' => 'embed', 'props' => ['url' => 'https://example.com/video']];
        $id    = $this->storedPage(['main' => $embed]);
        cms()->blockTypes()->restrict('embed', Permission::THEMES_CODE_MANAGE);

        [$status] = $this->store(['main' => $embed], id: $id, title: 'Only the title changed');
        self::assertSame(200, $status);

        [$status, $payload] = $this->store(['main' => ['type' => 'embed', 'props' => ['url' => 'https://example.com/other']]], id: $id);
        self::assertSame(400, $status);
        self::assertContains(
            'blocks.main: you are not allowed to add or change blocks of type "embed".',
            $payload['errors']['blocks'] ?? [],
        );

        [$status, $payload] = $this->store(['main' => $embed]);
        self::assertSame(400, $status, 'a new page counts as adding the block');

        $this->actAs(Permission::PAGES_CREATE, Permission::THEMES_CODE_MANAGE);
        [$status] = $this->store(['main' => ['type' => 'embed', 'props' => ['url' => 'https://example.com/other']]], id: $id);
        self::assertSame(200, $status);
    }

    /**
     * @param array<string, mixed> $blocks
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function store(array $blocks, int $id = 0, string $title = 'Placement'): array
    {
        JsonRequest::bind([
            'builderVersion' => PageBuilderPayloadNormalizer::VERSION,
            'id'             => $id,
            'title'          => $title,
            'path'           => $id > 0 ? '/placement-' . $id : '/placement-new-' . bin2hex(random_bytes(3)),
            'language'       => 'en',
            'status'         => 'draft',
            'templateId'     => 'default',
            'blocks'         => $blocks,
            'meta'           => [],
        ], '/admin/pages');
        $response = (new StorePage())();

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<string, mixed> $blocks
     */
    private function storedPage(array $blocks): int
    {
        $id = cms()->pages()->save([
            'identifier'  => 'placement',
            'path'        => '/placement-pending',
            'title'       => 'Placement',
            'language'    => 'en',
            'template_id' => 'default',
            'blocks'      => $blocks,
            'meta'        => [],
        ]);
        $this->pdo->exec('UPDATE pages SET path = \'/placement-' . $id . '\' WHERE id = ' . $id);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $text): array
    {
        return ['type' => 'quote', 'props' => ['quote' => $text]];
    }

    private function actAs(Permission ...$permissions): void
    {
        user(new UserHelper(null, $permissions, [], true));
    }
}
