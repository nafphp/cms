<?php

declare(strict_types=1);

namespace Naf\CMS\Storage;

/**
 * @internal
 */
final readonly class StorageQuery
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public string $sql,
        public array $parameters,
    ) {
    }
}
