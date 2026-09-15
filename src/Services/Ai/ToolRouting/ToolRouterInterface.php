<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Ai\ToolRouting;

interface ToolRouterInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $input, ToolContext $context, int $limit = 8): array;
}
