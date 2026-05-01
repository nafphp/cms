<?php

declare(strict_types=1);

namespace Cms\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use function NixPHP\app;

final class SystemHealthTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_system_health';
    }

    public function description(): string
    {
        return 'Returns basic NixCMS health information for PHP, storage paths, and loaded NixPHP plugins.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $basePath = rtrim((string) app()->getBasePath(), '/');

        return [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
            ],
            'storage' => [
                'logs' => $this->directoryStatus($basePath . '/logs'),
                'storage' => $this->directoryStatus($basePath . '/storage'),
                'storage/mcp' => $this->directoryStatus($basePath . '/storage/mcp'),
                'storage/queue' => $this->directoryStatus($basePath . '/storage/queue'),
            ],
            'plugins' => array_map(
                static fn($plugin) => [
                    'loaded' => true,
                    'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : null,
                ],
                app()->getPlugins()
            ),
        ];
    }

    private function directoryStatus(string $path): array
    {
        return [
            'exists' => is_dir($path),
            'readable' => is_dir($path) && is_readable($path),
            'writable' => is_dir($path) && is_writable($path),
        ];
    }
}
