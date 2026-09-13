<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ToolRouting;

use Naf\CMS\Services\Ai\LocalAiToolCatalog;
use Naf\CMS\Services\Ai\ToolRouting\KeywordToolRouter;
use Naf\CMS\Services\Ai\ToolRouting\SemanticToolRouter;
use Naf\CMS\Services\Ai\ToolRouting\ToolContext;
use Naf\CMS\Services\Ai\ToolRouting\ToolHardFilter;
use PHPUnit\Framework\TestCase;

final class SemanticToolRouterTest extends TestCase
{
    public function testRoutesLargeToolCatalogToSmallRelevantSlice(): void
    {
        $tools = [];
        for ($i = 0; $i < 120; $i++) {
            $tools[] = [
                'name' => 'plugin_misc_tool_' . $i,
                'description' => 'Generic plugin utility for unrelated maintenance task ' . $i,
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ];
        }
        $tools[] = [
            'name' => 'cms_page_overview',
            'description' => 'Returns page and layout block overview for the PageBuilder.',
            'inputSchema' => ['type' => 'object', 'properties' => ['pageId' => ['type' => 'string']]],
        ];
        $tools[] = [
            'name' => 'cms_tools_search',
            'description' => 'Searches the registered CMS tools for the current request and UI context.',
            'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
        ];

        $selected = (new SemanticToolRouter((new LocalAiToolCatalog())->enrich($tools)))->select(
            'Welche Bloecke sind in diesem Layout?',
            ToolContext::fromArray([
                'area' => 'pageBuilder',
                'selectedRegion' => 'content',
                'availableBlockModules' => ['text', 'image'],
            ]),
            8,
        );

        self::assertLessThanOrEqual(8, count($selected));
        self::assertSame('cms_page_overview', $selected[0]['name']);
        self::assertContains('cms_tools_search', array_column($selected, 'name'));
    }

    public function testHardFilterKeepsWriteToolsOutForReadOnlyRequests(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates an article draft.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_article_status_counts',
                'description' => 'Returns article status counts.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $selected = (new SemanticToolRouter($tools))->select(
            'Wie viele Artikel sind im Review?',
            ToolContext::fromArray(['area' => 'articleEditor']),
            5,
        );

        self::assertSame(['cms_article_status_counts'], array_column($selected, 'name'));
    }

    public function testUnknownIntentReturnsSearchToolInsteadOfGenericReadTools(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_queue_status',
                'description' => 'Returns queue status.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_page_overview',
                'description' => 'Returns page overview.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_tools_search',
                'description' => 'Searches the registered CMS tools for the current request and UI context.',
                'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            ],
        ]);

        $selected = (new SemanticToolRouter($tools))->select(
            'Sternenstaub Quantennebel Galaxiewunsch',
            ToolContext::fromArray([]),
            8,
        );

        self::assertSame(['cms_tools_search'], array_column($selected, 'name'));
        self::assertSame(['no-match-search'], $selected[0]['route']['reasons']);
    }

    public function testModeAndCapabilityFiltersAreAppliedBeforeRanking(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_queue_status',
                'description' => 'Returns queue status.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates an article draft.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $selected = (new SemanticToolRouter($tools))->select(
            'Erstelle einen Artikelentwurf',
            ToolContext::fromArray([
                'area' => 'articleEditor',
                'mode' => 'write',
                'capabilities' => ['articles.write'],
            ]),
            5,
        );

        self::assertSame(['cms_article_draft_create'], array_column($selected, 'name'));
    }

    public function testEmptyContextRejectsWriteToolsByDefaultButKeepsReadTools(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates an article draft.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_article_status_counts',
                'description' => 'Returns article status counts.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $filtered = (new ToolHardFilter())->filter(
            $tools,
            'create an article draft and show status counts',
            ToolContext::fromArray([]),
        );

        self::assertSame(['cms_article_status_counts'], array_column($filtered, 'name'));
    }

    /**
     * The keyword router matches English tool vocabulary. Without German alias
     * expansion every German phrase scored zero and the router degraded to
     * returning cms_tools_search only.
     */
    public function testGermanPhrasesRouteToMatchingTools(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_admin_navigate',
                'description' => 'Navigates the current admin UI to a safe registered NafCMS admin area such as PageBuilder, articles, forms, themes, media, users, roles, navigation, or settings.',
                'inputSchema' => ['type' => 'object', 'properties' => ['target' => ['type' => 'string']]],
            ],
            [
                'name' => 'cms_user_role_summary',
                'description' => 'Returns a summary of users, roles and permissions.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_system_health',
                'description' => 'Returns current NafCMS system health data.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_tools_search',
                'description' => 'Searches the registered CMS tools for the current request and UI context.',
                'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            ],
        ]);

        $expectations = [
            'Bringe mich zu den Benutzern' => 'cms_admin_navigate',
            'Bringe mich zu den Rollen' => 'cms_admin_navigate',
            'Wie ist die Systemgesundheit?' => 'cms_system_health',
        ];

        foreach ($expectations as $phrase => $expected) {
            $selected = (new KeywordToolRouter($tools))->select($phrase, ToolContext::fromArray([]), 5);
            $names = array_column($selected, 'name');

            self::assertSame($expected, $names[0] ?? '', $phrase);
            self::assertNotSame(['cms_tools_search'], $names, $phrase);
        }
    }

    public function testGermanUserPhraseAlsoSurfacesTheUserRoleTool(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_user_role_summary',
                'description' => 'Returns a summary of users, roles and permissions.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_queue_status',
                'description' => 'Returns the current queue status.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $selected = (new KeywordToolRouter($tools))->select(
            'Zeig mir die Benutzer und ihre Rollen',
            ToolContext::fromArray([]),
            5,
        );

        self::assertSame('cms_user_role_summary', $selected[0]['name'] ?? '');
    }
}
