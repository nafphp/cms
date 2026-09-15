<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Ai\ToolRouting;

use function Naf\CMS\audit_log;
use function Naf\CMS\cms;
use function Naf\log;

final class SemanticToolRouter implements ToolRouterInterface
{
    /**
     * @param array<int, array<string, mixed>> $tools
     */
    public function __construct(
        private array $tools,
        private ?ToolRouterInterface $fallback = null,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $input, ToolContext $context, int $limit = 8): array
    {
        $fallback       = $this->fallback ?? new KeywordToolRouter($this->tools);
        $embeddingModel = trim((string) cms()->settings()->value('ai.embedding_model', ''));
        if ($embeddingModel !== '') {
            $router = new CosineToolRouter(
                $this->tools,
                new OllamaEmbeddingClient(
                    (string) cms()->settings()->value('ai.url', 'http://localhost:11434'),
                    $embeddingModel,
                    (float) cms()->settings()->value('ai.embedding_timeout', 1.5),
                ),
                fallback: $fallback,
            );

            return $router->select($input, $context, $limit);
        }

        $this->recordFallback('embedding_model_missing', $input, $context);

        return $fallback->select($input, $context, $limit);
    }

    private function recordFallback(string $reason, string $input, ToolContext $context): void
    {
        $meta = (new RoutingTelemetry())->fallback($reason, $input, $context);

        audit_log('ai.routing.fallback', 'router', null, null, null, $meta);
        log()->info('ai.routing.fallback', $meta);
    }
}
