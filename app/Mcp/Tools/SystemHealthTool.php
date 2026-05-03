<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PDO;
use Throwable;
use function NixPHP\app;
use function NixPHP\config;
use function NixPHP\Database\database;

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
            'database' => $this->databaseStatus(),
            'session' => [
                'storage' => config('session:storage', null),
                'database_table' => config('session:database_table', null),
            ],
            'mcp_auth' => [
                'enabled' => (bool) config('mcp:auth:enabled', true),
                'driver' => config('mcp:auth:driver', null),
                'token_file' => $this->fileStatus((string) config('mcp:auth:token_file', $basePath . '/storage/mcp/tokens.json')),
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

    private function databaseStatus(): array
    {
        try {
            $connection = database();
            if (!$connection instanceof PDO) {
                return ['available' => false];
            }

            $connection->query('SELECT 1');

            return [
                'available' => true,
                'driver' => (string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME),
                'server_version' => (string) $connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            ];
        } catch (Throwable $throwable) {
            return [
                'available' => false,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    private function directoryStatus(string $path): array
    {
        return [
            'exists' => is_dir($path),
            'readable' => is_dir($path) && is_readable($path),
            'writable' => is_dir($path) && is_writable($path),
        ];
    }

    private function fileStatus(string $path): array
    {
        return [
            'exists' => is_file($path),
            'readable' => is_file($path) && is_readable($path),
            'writable' => is_file($path) ? is_writable($path) : is_writable(dirname($path)),
        ];
    }
}
