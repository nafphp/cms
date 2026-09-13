<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Http;

final readonly class OutboundHttpTarget
{
    /** @param list<string> $addresses */
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public array $addresses,
    ) {
    }
}
