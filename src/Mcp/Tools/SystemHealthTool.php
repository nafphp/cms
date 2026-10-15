<?php

declare(strict_types=1);

namespace Naf\CMS\Mcp\Tools;

use DateTimeImmutable;
use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Services\Assets\AssetPublisher;
use Naf\CMS\Support\RuntimePaths;
use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;
use PDO;
use Throwable;

use function Naf\app;
use function Naf\config;
use function Naf\Database\database;

final class SystemHealthTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    public function localAiMetadata(): array
    {
        return [
            'title'        => 'System health',
            'routingText'  => 'Inspect PHP, database, session, runtime storage, assets, and loaded plugin health.',
            'examples'     => ['Is the CMS healthy?', 'Show database and asset status.'],
            'capabilities' => ['system.read'],
            'mode'         => 'read',
            'risk'         => 'read',
            'destructive'  => false,
            'autoRun'      => true,
        ];
    }

    public function name(): string
    {
        return 'cms_system_health';
    }

    public function description(): string
    {
        return implode(' ', [
            'Returns current NafCMS system health data.',
            'The result includes php.version, php.memory_limit, database.available, database.driver, database.server_version, session storage settings, MCP auth token-file status, host runtime directories, published asset status, and loaded NAF plugins.',
            'When summarizing this tool result for a user, use the exact returned values and present all returned sections as compact Markdown tables.',
            'Use tables for PHP, database, session, MCP auth, storage, and plugins when those sections are present; do not render scalar diagnostics only as label-colon text when they can be represented as table rows.',
            'Do not say that PHP version, database version, modules, storage, or plugin data are missing when the returned JSON contains those fields.',
            'Do not invent modules or health checks that are not present in the result.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $basePath = rtrim((string) app()->getBasePath(), '/');

        return [
            'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'php'          => [
                'version'      => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
            ],
            'database' => $this->databaseStatus(),
            'session'  => [
                'storage'        => config('session:storage', null),
                'database_table' => config('session:database_table', null),
            ],
            'mcp_auth' => [
                'enabled'    => (bool) config('mcp:auth:enabled', true),
                'driver'     => config('mcp:auth:driver', null),
                'token_file' => $this->fileStatus((string) config('mcp:auth:token_file', $basePath . '/storage/mcp/tokens.json')),
            ],
            'storage' => [
                'logs'                => $this->directoryStatus($basePath . '/logs'),
                'storage'             => $this->directoryStatus($basePath . '/storage'),
                'storage/mcp'         => $this->directoryStatus($basePath . '/storage/mcp'),
                'storage/queue'       => $this->directoryStatus($basePath . '/storage/queue'),
                'nafcms_runtime'      => $this->directoryStatus(RuntimePaths::storageRoot()),
                'nafcms_prompts'      => $this->directoryStatus(RuntimePaths::prompts()),
                'nafcms_translations' => $this->directoryStatus(RuntimePaths::translations()),
            ],
            'assets'  => $this->assetStatus(),
            'plugins' => array_map(
                static fn($plugin) => [
                    'loaded'  => true,
                    'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : null,
                ],
                app()->getPlugins(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assetStatus(): array
    {
        $status = (new AssetPublisher())->status();

        return [
            'current'   => $status['current'],
            'missing'   => count($status['missing']),
            'outdated'  => count($status['outdated']),
            'stale'     => count($status['stale']),
            'conflicts' => count($status['conflicts']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseStatus(): array
    {
        try {
            $connection = database();
            if (!$connection instanceof PDO) {
                return ['available' => false];
            }

            $connection->query('SELECT 1');

            return [
                'available'      => true,
                'driver'         => (string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME),
                'server_version' => (string) $connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            ];
        } catch (Throwable $throwable) {
            return [
                'available' => false,
                'error'     => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function directoryStatus(string $path): array
    {
        return [
            'exists'   => is_dir($path),
            'readable' => is_dir($path) && is_readable($path),
            'writable' => is_dir($path) && is_writable($path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileStatus(string $path): array
    {
        return [
            'exists'   => is_file($path),
            'readable' => is_file($path) && is_readable($path),
            'writable' => is_file($path) ? is_writable($path) : is_writable(dirname($path)),
        ];
    }
}
