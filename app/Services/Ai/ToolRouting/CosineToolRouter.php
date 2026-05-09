<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai\ToolRouting;

final class CosineToolRouter implements ToolRouterInterface
{
    private const float MINIMUM_RELEVANCE_SCORE = 0.24;

    /**
     * @param array<int, array<string, mixed>> $tools
     */
    public function __construct(
        private array $tools,
        private EmbeddingClientInterface $embeddings,
        private ToolHardFilter $hardFilter = new ToolHardFilter(),
        private ?ToolRouterInterface $fallback = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $input, ToolContext $context, int $limit = 8): array
    {
        $limit = max(1, min(32, $limit));
        $candidates = $this->hardFilter->filter($this->tools, $input, $context);
        $queryText = $this->queryText($input, $context);
        $queryEmbedding = $this->embeddings->embed($queryText);
        if ($queryEmbedding === null || $candidates === []) {
            return $this->fallback()->select($input, $context, $limit);
        }

        $toolTexts = array_map(fn(array $tool): string => $this->toolText($tool), $candidates);
        $toolEmbeddings = $this->embeddings->embedMany($toolTexts);

        $routes = [];
        foreach ($candidates as $index => $tool) {
            $toolEmbedding = $toolEmbeddings[$index] ?? null;
            if ($toolEmbedding === null) {
                return $this->fallback()->select($input, $context, $limit);
            }

            $score = $this->cosineSimilarity($queryEmbedding, $toolEmbedding);
            if ($score < self::MINIMUM_RELEVANCE_SCORE) {
                continue;
            }

            $routes[] = new ToolRoute($tool, $score, ['cosine']);
        }

        usort($routes, static fn(ToolRoute $left, ToolRoute $right): int => $right->score <=> $left->score);

        $selected = array_map(static fn(ToolRoute $route): array => [
            ...$route->tool,
            'route' => [
                'score' => $route->score,
                'reasons' => $route->reasons,
            ],
        ], array_slice($routes, 0, $limit));

        return $selected !== []
            ? $this->withSearchTool($selected, $candidates, $limit)
            : $this->fallback()->select($input, $context, $limit);
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function toolText(array $tool): string
    {
        $meta = is_array($tool['meta'] ?? null) ? $tool['meta'] : [];

        return trim(implode(' ', [
            (string) ($tool['name'] ?? ''),
            (string) ($tool['description'] ?? ''),
            (string) ($tool['routingDescription'] ?? ''),
            (string) ($meta['routingText'] ?? ''),
            implode(' ', is_array($meta['examples'] ?? null) ? $meta['examples'] : []),
            implode(' ', is_array($meta['capabilities'] ?? null) ? $meta['capabilities'] : []),
        ]));
    }

    private function queryText(string $input, ToolContext $context): string
    {
        $parts = [$input, $context->area, $context->pageId, $context->layoutId, $context->themeId, $context->contentType];
        foreach (['selectedRegion', 'insertAfterBlockId', 'theme'] as $key) {
            if (isset($context->uiContext[$key]) && is_scalar($context->uiContext[$key])) {
                $parts[] = (string) $context->uiContext[$key];
            }
        }
        if (isset($context->uiContext['availableBlockModules']) && is_array($context->uiContext['availableBlockModules'])) {
            $parts[] = implode(' ', array_filter($context->uiContext['availableBlockModules'], 'is_scalar'));
        }

        return implode(' ', array_filter($parts, static fn(mixed $part): bool => is_scalar($part) && trim((string) $part) !== ''));
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        $size = min(count($left), count($right));
        if ($size === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;
        for ($index = 0; $index < $size; $index++) {
            $dot += $left[$index] * $right[$index];
            $leftMagnitude += $left[$index] * $left[$index];
            $rightMagnitude += $right[$index] * $right[$index];
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }

    private function fallback(): ToolRouterInterface
    {
        return $this->fallback ??= new KeywordToolRouter($this->tools);
    }

    /**
     * @param array<int, array<string, mixed>> $selected
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function withSearchTool(array $selected, array $candidates, int $limit): array
    {
        if ($limit < 4 || count($selected) >= $limit || in_array('cms_tools_search', array_column($selected, 'name'), true)) {
            return $selected;
        }

        foreach ($candidates as $candidate) {
            if (($candidate['name'] ?? '') !== 'cms_tools_search') {
                continue;
            }

            $selected[] = [
                ...$candidate,
                'route' => [
                    'score' => 0.1,
                    'reasons' => ['fallback-search'],
                ],
            ];
            break;
        }

        return $selected;
    }
}
