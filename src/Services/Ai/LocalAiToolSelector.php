<?php

declare(strict_types=1);

namespace src\Services\Ai;

use src\Services\Ai\ToolRouting\SemanticToolRouter;
use src\Services\Ai\ToolRouting\ToolContext;

/**
 * Backwards-compatible facade for the local assistant.
 *
 * New code should depend on ToolRouterInterface/SemanticToolRouter directly.
 */
final class LocalAiToolSelector
{
    /**
     * @param array<int, array<string, mixed>> $definitions
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function select(array $definitions, string $message, array $context = [], int $limit = 12): array
    {
        return (new SemanticToolRouter($definitions))->select(
            $message,
            ToolContext::fromArray($context),
            $limit,
        );
    }
}
