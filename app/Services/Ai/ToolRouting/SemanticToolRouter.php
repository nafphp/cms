<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai\ToolRouting;
use function NixCMS\cms;

/**
 * Current semantic router implementation.
 *
 * It intentionally starts without a network/embedding dependency: tools are ranked
 * by routing descriptions, examples, capabilities, mode, and UI context. An Ollama
 * embedding scorer can be added behind this class without changing assistant callers.
 */
final class SemanticToolRouter implements ToolRouterInterface
{
    /**
     * @param array<int, array<string, mixed>> $tools
     */
    public function __construct(
        private array $tools,
        private ?ToolRouterInterface $fallback = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $input, ToolContext $context, int $limit = 8): array
    {
        $embeddingModel = trim((string) cms()->settings()->value('ai.embedding_model', ''));
        if ($embeddingModel !== '') {
            $router = new CosineToolRouter(
                $this->tools,
                new OllamaEmbeddingClient(
                    (string) cms()->settings()->value('ai.url', 'http://localhost:11434'),
                    $embeddingModel,
                ),
                fallback: $this->fallback ?? new KeywordToolRouter($this->tools),
            );

            return $router->select($input, $context, $limit);
        }

        $router = $this->fallback ?? new KeywordToolRouter($this->tools);

        return $router->select($input, $context, $limit);
    }
}
