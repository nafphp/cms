<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Ai\ToolRouting;

final readonly class ToolRoute
{
    /**
     * @param array<string, mixed> $tool
     * @param array<int, string> $reasons
     */
    public function __construct(
        public array $tool,
        public float $score,
        public array $reasons = [],
    ) {
    }
}
