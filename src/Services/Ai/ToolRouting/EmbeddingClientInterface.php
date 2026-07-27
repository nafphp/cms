<?php

declare(strict_types=1);

namespace src\Services\Ai\ToolRouting;

interface EmbeddingClientInterface
{
    /**
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array;

    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, float>|null>
     */
    public function embedMany(array $texts): array;
}
