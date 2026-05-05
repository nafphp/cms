<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ToolRouting;

use NixCMS\Services\Ai\LocalAiToolCatalog;
use NixCMS\Services\Ai\ToolRouting\CosineToolRouter;
use NixCMS\Services\Ai\ToolRouting\EmbeddingClientInterface;
use NixCMS\Services\Ai\ToolRouting\ToolContext;
use PHPUnit\Framework\TestCase;

final class CosineToolRouterTest extends TestCase
{
    public function testRanksToolsByCosineSimilarityAfterHardFilter(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name' => 'cms_queue_status',
                'description' => 'Returns queue status and pending jobs.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'cms_stage_create_change',
                'description' => 'Creates a staged CMS change for page blocks.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $selected = (new CosineToolRouter($tools, new FakeEmbeddingClient()))->select(
            'Füge hier einen Textblock ein',
            ToolContext::fromArray(['area' => 'pageBuilder']),
            2,
        );

        self::assertSame('cms_stage_create_change', $selected[0]['name']);
        self::assertSame(['cosine'], $selected[0]['route']['reasons']);
    }
}

final class FakeEmbeddingClient implements EmbeddingClientInterface
{
    public function embed(string $text): ?array
    {
        return $this->vector($text);
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn(string $text): ?array => $this->vector($text), $texts);
    }

    /**
     * @return array<int, float>
     */
    private function vector(string $text): array
    {
        $text = strtolower($text);

        return str_contains($text, 'block') || str_contains($text, 'stage') || str_contains($text, 'füge')
            ? [1.0, 0.0]
            : [0.0, 1.0];
    }
}
