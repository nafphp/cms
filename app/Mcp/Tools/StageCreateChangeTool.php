<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixCMS\Stage\Stage;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;

final class StageCreateChangeTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_stage_create_change';
    }

    public function description(): string
    {
        return 'Creates a staged CMS change for a target such as a page. Does not publish.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('target', Schema::object()
                ->additionalProperties(true)
                ->prop('type', Schema::string()->default('page'))
                ->prop('id', Schema::integer()->min(1))
                ->prop('snapshot', Schema::object()->additionalProperties(true)->description('Optional current UI snapshot for unsaved or locally edited targets.'))
                ->required('type'))
            ->prop('change', Schema::object()
                ->additionalProperties(true)
                ->prop('type', Schema::string()->description('Change type, for example block.add or theme.set.'))
                ->prop('payload', Schema::object()->additionalProperties(true))
                ->required('type'))
            ->required('target', 'change')
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['pages:write', 'themes:write', 'content:write'];
    }

    public function handle(array $args): array
    {
        $targetPayload = is_array($args['target'] ?? null) ? $args['target'] : [];
        $changePayload = is_array($args['change'] ?? null) ? $args['change'] : [];
        $changeType = trim((string) ($changePayload['type'] ?? ''));
        $payload = is_array($changePayload['payload'] ?? null) ? $changePayload['payload'] : [];

        $target = \cms()->stageTargets()->resolve($targetPayload);
        $stage = Stage::create($target)->change($changeType, $payload);

        return [
            'success' => true,
            'stageId' => $stage->id(),
            'target' => [
                'type' => $target->type(),
                'id' => $target->id(),
            ],
            'status' => $stage->status(),
            'changeCount' => count($stage->changes()),
            'uiActions' => $stage->uiActions(),
            'warnings' => $stage->warnings(),
            'commitRequired' => true,
            'publishRequired' => true,
            'message' => 'Change staged. Commit is required to save it; publish remains a separate action.',
        ];
    }
}
