<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\CMS\Contracts\LocalAiToolMetadataInterface;
use NixPHP\CMS\Enums\ToolRisk;

use NixPHP\CMS\Api\MediaApi;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;
use function NixPHP\CMS\cms;

final class MediaSummaryTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;
    public function __construct(private ?MediaApi $media = null) {}

    public function name(): string
    {
        return 'cms_media_summary';
    }

    public function description(): string
    {
        return 'Returns media library counts, byte totals, kind counts, and optional recent media items.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('include_recent', Schema::boolean()->default(false))
            ->prop('limit', Schema::integer()->min(1)->max(50)->default(10))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['media:read'];
    }

    public function handle(array $args): array
    {
        $includeRecent = filter_var($args['include_recent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));

        try {
            $summary = $this->media()->summary();
            $recent = $includeRecent
                ? array_map($this->summarizeMediaItem(...), $this->media()->recent($limit))
                : [];

            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => true,
                ...$summary,
                'recent' => $recent,
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'error' => $throwable->getMessage(),
                'total' => 0,
                'images' => 0,
                'documents' => 0,
                'archives' => 0,
                'files' => 0,
                'bytes' => 0,
                'kind_counts' => [
                    'image' => 0,
                    'document' => 0,
                    'archive' => 0,
                    'file' => 0,
                ],
                'recent' => [],
            ];
        }
    }

    private function media(): MediaApi
    {
        return $this->media ?? cms()->media();
    }

    /**
     * @param array<string, mixed> $media
     * @return array<string, mixed>
     */
    private function summarizeMediaItem(array $media): array
    {
        return [
            'id' => (int) ($media['id'] ?? 0),
            'public_id' => (string) ($media['public_id'] ?? ''),
            'original_name' => (string) ($media['original_name'] ?? ''),
            'kind' => (string) ($media['kind'] ?? 'file'),
            'mime_type' => (string) ($media['mime_type'] ?? ''),
            'visibility' => (string) ($media['visibility'] ?? 'public'),
            'size_bytes' => (int) ($media['size_bytes'] ?? 0),
            'size_label' => (string) ($media['size_label'] ?? ''),
            'dimensions_label' => (string) ($media['dimensions_label'] ?? ''),
            'public_url' => (string) ($media['public_url'] ?? ''),
            'created_at' => (string) ($media['created_at'] ?? ''),
        ];
    }
}
