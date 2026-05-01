<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;

final class ArticleStatusCountsTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_article_status_counts';
    }

    public function description(): string
    {
        return 'Returns article workflow status counts. This placeholder is ready for the first content migration.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('language', Schema::string()->default('all'))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['articles:read'];
    }

    public function handle(array $args): array
    {
        return [
            'metric' => 'latest_article_versions_by_status',
            'language' => strtolower(trim((string) ($args['language'] ?? 'all'))),
            'status_values' => ['draft', 'review', 'declined', 'published', 'scheduled', 'archived'],
            'counts' => [],
            'total' => 0,
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
