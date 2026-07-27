<?php

declare(strict_types=1);

namespace src\Contracts;

/** Optional self-description consumed by LocalAiToolCatalog and the admin assistant. */
interface LocalAiToolMetadataInterface
{
    /**
     * @return array{
     *     title?: string,
     *     routingText?: string,
     *     examples?: array<int, string>,
     *     capabilities?: array<int, string>,
     *     mode?: string,
     *     risk?: string,
     *     destructive?: bool,
     *     autoRun?: bool,
     *     category?: string,
     *     source?: string
     * }
     */
    public function localAiMetadata(): array;
}
