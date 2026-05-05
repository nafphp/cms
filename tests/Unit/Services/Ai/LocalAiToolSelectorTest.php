<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use NixCMS\Services\Ai\LocalAiToolCatalog;
use NixCMS\Services\Ai\LocalAiToolSelector;
use PHPUnit\Framework\TestCase;

final class LocalAiToolSelectorTest extends TestCase
{
    public function testSelectsContextRelevantToolsBeforePromptingModel(): void
    {
        $tools = $this->tools();

        $selected = (new LocalAiToolSelector())->select(
            $tools,
            'Wie sieht der Status der Queue aus?',
            ['area' => 'dashboard'],
            2
        );

        self::assertSame('cms_queue_status', $selected[0]['name']);
    }

    public function testAvoidsWriteToolsWhenUserOnlyReadsCmsState(): void
    {
        $tools = $this->tools();

        $selected = (new LocalAiToolSelector())->select(
            $tools,
            'Welche Artikel gibt es gerade?',
            ['area' => 'articleEditor'],
            3
        );

        self::assertNotContains('cms_article_draft_create', array_column($selected, 'name'));
    }

    public function testPageBuilderContextBoostsPageTools(): void
    {
        $tools = $this->tools();

        $selected = (new LocalAiToolSelector())->select(
            $tools,
            'Fuege hier einen Textblock ein',
            [
                'area' => 'pageBuilder',
                'selectedRegion' => 'content',
                'availableBlockModules' => ['text', 'image'],
            ],
            2
        );

        self::assertSame('cms_stage_create_change', $selected[0]['name']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        return (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_stage_create_change',
                'description' => 'Creates a staged CMS change for a target such as a page. Does not publish.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_queue_status',
                'description' => 'Returns queue status and pending jobs.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_article_draft_create',
                'description' => 'Creates an article draft.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_page_overview',
                'description' => 'Returns page and layout block overview.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);
    }
}
