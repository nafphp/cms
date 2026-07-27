<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Ai\ToolRouting;

use NixPHP\CMS\Services\Ai\ToolRouting\ToolContext;

interface ToolRouterInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $input, ToolContext $context, int $limit = 8): array;
}
