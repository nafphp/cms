<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixCMS\Repositories\StageRepository;
use NixCMS\Stage\Stage;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;

final class StagePreviewTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_stage_preview';
    }

    public function description(): string
    {
        return 'Returns the staged working snapshot, change list, warnings, and UI actions for a CMS stage.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('stageId', Schema::integer()->min(1))
            ->required('stageId')
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['pages:read', 'themes:read', 'content:read'];
    }

    public function handle(array $args): array
    {
        $repository = new StageRepository();
        $row = $repository->find((int) ($args['stageId'] ?? 0));
        if (!is_array($row)) {
            throw new \InvalidArgumentException('Stage does not exist.');
        }

        $target = \cms()->stageTargets()->resolve([
            'type' => (string) ($row['target_type'] ?? ''),
            'id' => (string) ($row['target_id'] ?? ''),
        ]);
        $stage = Stage::fromRow($target, $row, $repository);

        return [
            'success' => true,
            ...$stage->preview(),
            'commitRequired' => $stage->status() === 'open',
            'publishRequired' => true,
        ];
    }
}
