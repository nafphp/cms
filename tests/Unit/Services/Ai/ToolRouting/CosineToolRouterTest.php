<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ToolRouting;

use Naf\CMS\Services\Ai\LocalAiToolCatalog;
use Naf\CMS\Services\Ai\ToolRouting\CosineToolRouter;
use Naf\CMS\Services\Ai\ToolRouting\EmbeddingClientInterface;
use Naf\CMS\Services\Ai\ToolRouting\ToolContext;
use PHPUnit\Framework\TestCase;

final class CosineToolRouterTest extends TestCase
{
    public function testRanksToolsByCosineSimilarityAfterHardFilter(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name'        => 'cms_queue_status',
                'description' => 'Returns queue status and pending jobs.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'cms_article_draft_create',
                'description' => 'Creates an article draft from a requested title and content.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $selected = (new CosineToolRouter($tools, new FakeEmbeddingClient()))->select(
            'Erstelle einen Artikelentwurf',
            ToolContext::fromArray(['area' => 'articleEditor']),
            2,
        );

        self::assertSame('cms_article_draft_create', $selected[0]['name']);
        self::assertSame(['cosine'], $selected[0]['route']['reasons']);
    }

    public function testFallsBackToSearchToolWhenCosineConfidenceIsTooLow(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name'        => 'cms_queue_status',
                'description' => 'Returns queue status.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'cms_page_overview',
                'description' => 'Returns page overview.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'cms_tools_search',
                'description' => 'Searches the registered CMS tools.',
                'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            ],
        ]);

        $selected = (new CosineToolRouter($tools, new LowConfidenceEmbeddingClient()))->select(
            'Kannst du etwas komplett Undefiniertes finden?',
            ToolContext::fromArray([]),
            8,
        );

        self::assertSame(['cms_tools_search'], array_column($selected, 'name'));
        self::assertSame(['no-match-search'], $selected[0]['route']['reasons']);
    }

    public function testMinimumRelevanceScoreSeparatesMatchingAndWeakEmbeddings(): void
    {
        $tools = (new LocalAiToolCatalog())->enrich([
            [
                'name'        => 'cms_strong_match',
                'description' => 'Returns a strong semantic match.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'cms_weak_match',
                'description' => 'Returns a weak semantic match.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ]);

        $selected = (new CosineToolRouter($tools, new ThresholdEmbeddingClient()))->select(
            'threshold probe',
            ToolContext::fromArray(['area' => 'dashboard']),
            5,
        );

        self::assertSame(['cms_strong_match'], array_column($selected, 'name'));
    }
}

final class FakeEmbeddingClient implements EmbeddingClientInterface
{
    public function embed(string $text): array
    {
        return $this->vector($text);
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn(string $text): array => $this->vector($text), $texts);
    }

    /**
     * @return array<int, float>
     */
    private function vector(string $text): array
    {
        $text = strtolower($text);

        return str_contains($text, 'artikel') || str_contains($text, 'article') || str_contains($text, 'draft') || str_contains($text, 'entwurf') || str_contains($text, 'erstelle')
            ? [1.0, 0.0]
            : [0.0, 1.0];
    }
}

final class LowConfidenceEmbeddingClient implements EmbeddingClientInterface
{
    public function embed(string $text): array
    {
        return [1.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(static fn(): array => [0.1, 0.995], $texts);
    }
}

final class ThresholdEmbeddingClient implements EmbeddingClientInterface
{
    public function embed(string $text): array
    {
        return [1.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(static function (string $text): array {
            return str_contains($text, 'strong')
                ? [0.25, sqrt(1 - (0.25 ** 2))]
                : [0.23, sqrt(1 - (0.23 ** 2))];
        }, $texts);
    }
}
