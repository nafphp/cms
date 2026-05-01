<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use function NixPHP\app;

final class QueueStatusTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_queue_status';
    }

    public function description(): string
    {
        return 'Returns file-backed queue counts for pending, locked, and deadletter jobs.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('channel', Schema::string()->default('default'))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['queue:read'];
    }

    public function handle(array $args): array
    {
        $channel = strtolower(trim((string) ($args['channel'] ?? 'default')));
        $basePath = rtrim((string) app()->getBasePath(), '/') . '/storage/queue';

        return [
            'channel' => $channel,
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'pending_jobs' => $this->countFiles($basePath, $channel, '*.job'),
            'active_workers' => $this->countFiles($basePath, $channel, '*.job.lock'),
            'deadletter_jobs' => $this->countFiles($basePath . '/deadletter', $channel, '*.job'),
        ];
    }

    private function countFiles(string $basePath, string $channel, string $pattern): int
    {
        if ($channel === 'all') {
            $count = 0;
            foreach (glob($basePath . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
                $count += count(glob($directory . '/' . $pattern) ?: []);
            }
            return $count;
        }

        $path = $basePath . '/' . $channel;
        return is_dir($path) ? count(glob($path . '/' . $pattern) ?: []) : 0;
    }
}
