<?php

declare(strict_types=1);

namespace Naf\CMS\Mcp\Tools;

use DateTimeImmutable;
use InvalidArgumentException;
use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Enums\ToolRisk;
use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;

use function Naf\app;

final class QueueStatusTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK    = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;

    public function __construct(private ?string $queuePath = null)
    {
    }

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
        $channel  = $this->normalizeChannel($args['channel'] ?? 'default');
        $basePath = $this->queuePath();

        return [
            'channel'            => $channel,
            'generated_at'       => (new DateTimeImmutable())->format(DATE_ATOM),
            'available_channels' => $this->channels($basePath),
            'pending_jobs'       => $this->countFiles($basePath, $channel, '*.job'),
            'active_workers'     => $this->countFiles($basePath, $channel, '*.job.lock'),
            'deadletter_jobs'    => $this->countFiles($basePath . '/deadletter', $channel, '*.job'),
        ];
    }

    private function normalizeChannel(mixed $channel): string
    {
        $normalized = strtolower(trim((string) $channel));
        if ($normalized === '') {
            return 'default';
        }

        if ($normalized === 'all') {
            return $normalized;
        }

        if (preg_match('/^[a-z0-9_.-]+$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Queue channel must contain only letters, numbers, dots, dashes, or underscores.');
        }

        return $normalized;
    }

    private function queuePath(): string
    {
        if ($this->queuePath !== null) {
            return rtrim($this->queuePath, '/');
        }

        return rtrim((string) app()->getBasePath(), '/') . '/storage/queue';
    }

    /**
     * @return array<int, string>
     */
    private function channels(string $basePath): array
    {
        $channels = [];
        foreach (glob($basePath . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            if ($name === 'deadletter') {
                continue;
            }

            $channels[$name] = $name;
        }

        foreach (glob($basePath . '/deadletter/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name            = basename($directory);
            $channels[$name] = $name;
        }

        ksort($channels);

        return array_values($channels);
    }

    private function countFiles(string $basePath, string $channel, string $pattern): int
    {
        if ($channel === 'all') {
            $count = 0;
            foreach (glob($basePath . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
                if (basename($directory) === 'deadletter') {
                    continue;
                }

                $count += count(glob($directory . '/' . $pattern) ?: []);
            }

            return $count;
        }

        $path = $basePath . '/' . $channel;

        return is_dir($path) ? count(glob($path . '/' . $pattern) ?: []) : 0;
    }
}
