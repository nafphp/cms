<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Http;

final readonly class OutboundHttpProfile
{
    /**
     * @param list<string> $developmentHosts
     * @param list<int> $allowedPorts
     */
    private function __construct(
        public string $name,
        public array $schemes,
        public bool $allowPrivateNetworks,
        public array $developmentHosts = [],
        public array $allowedPorts = [],
    ) {
    }

    /** @param list<string> $developmentHosts */
    public static function webhook(bool $allowDevelopmentHttp = false, array $developmentHosts = []): self
    {
        $hosts = array_values(array_unique(array_filter(array_map(
            static fn(mixed $host): string => strtolower(trim((string) $host)),
            $developmentHosts,
        ))));

        return new self(
            'webhook',
            $allowDevelopmentHttp ? ['https', 'http'] : ['https'],
            $allowDevelopmentHttp && $hosts !== [],
            $hosts,
        );
    }

    /** @param list<int> $allowedPorts */
    public static function ollama(array $allowedPorts = [11434]): self
    {
        $ports = array_values(array_unique(array_filter(array_map('intval', $allowedPorts), static fn(int $port): bool => $port > 0 && $port <= 65535)));

        return new self('ollama', ['http', 'https'], true, ['localhost', '127.0.0.1', '::1'], $ports);
    }

    public static function publicHttps(): self
    {
        return new self('public_https', ['https'], false);
    }
}
