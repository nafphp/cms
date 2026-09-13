<?php

declare(strict_types=1);

namespace Naf\CMS\Mcp\Tools;

use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Enums\ToolRisk;

use Naf\CMS\Services\Ai\LocalAiToolBridge;
use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;

final class ToolSearchTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;
    public function name(): string
    {
        return 'cms_tools_search';
    }

    public function description(): string
    {
        return implode(' ', [
            'Searches the registered CMS tools for the current request and UI context.',
            'Use this when the currently visible tools do not clearly cover the user request.',
            'The result returns candidate tool definitions. It never answers the user request itself:',
            'always call one of the returned tools afterwards instead of describing them.',
        ]);
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('query', Schema::string()->description('Tool search query.')->default(''))
            ->prop('context', Schema::object()->description('Optional UI context such as area, pageId, selectedRegion, or theme.')->additionalProperties(true))
            ->prop('capabilities', Schema::array(Schema::string())->description('Optional requested capabilities.'))
            ->prop('mode', Schema::string()->description('Optional mode filter such as read or write.')->default(''))
            ->prop('limit', Schema::integer()->description('Maximum tools to return.')->min(1)->max(20)->default(8))
            ->required('query')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function requiredScopes(): array
    {
        return ['tools:read'];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $context = is_array($args['context'] ?? null) ? $args['context'] : [];
        if (is_array($args['capabilities'] ?? null)) {
            $context['capabilities'] = $args['capabilities'];
        }
        if (isset($args['mode']) && trim((string) $args['mode']) !== '') {
            $context['mode'] = strtolower(trim((string) $args['mode']));
        }

        $tools = (new LocalAiToolBridge())->selectDefinitions(
            trim((string) ($args['query'] ?? '')),
            $context,
            (int) ($args['limit'] ?? 8),
            includeSearchTool: false,
        );

        return [
            'success' => true,
            'count' => count($tools),
            'tools' => array_map(static fn(array $tool): array => [
                'name' => (string) ($tool['name'] ?? ''),
                'description' => (string) ($tool['description'] ?? ''),
                'inputSchema' => is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : [],
                'meta' => is_array($tool['meta'] ?? null) ? $tool['meta'] : [],
            ], $tools),
        ];
    }
}
