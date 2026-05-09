<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai\ToolRouting;
use function NixCMS\cms;

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
        $fallback = $this->fallback ?? new KeywordToolRouter($this->tools);
        $embeddingModel = trim((string) cms()->settings()->value('ai.embedding_model', ''));
        if ($embeddingModel !== '') {
            $router = new CosineToolRouter(
                $this->tools,
                new OllamaEmbeddingClient(
                    (string) cms()->settings()->value('ai.url', 'http://localhost:11434'),
                    $embeddingModel,
                ),
                fallback: $fallback,
            );

            return $router->select($input, $context, $limit);
        }

        return $fallback->select($input, $context, $limit);
    }
}
