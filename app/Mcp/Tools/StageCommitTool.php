<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixCMS\Repositories\StageRepository;
use NixCMS\Stage\Stage;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;

final class StageCommitTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_stage_commit';
    }

    public function description(): string
    {
        return 'Commits a previously staged CMS change into stored CMS state. Does not publish.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('stageId', Schema::integer()->min(1))
            ->prop('snapshot', Schema::object()->additionalProperties(true)->description('Optional current UI snapshot to commit instead of the stored working snapshot.'))
            ->required('stageId')
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['pages:write', 'themes:write', 'content:write'];
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
        if (isset($args['snapshot']) && is_array($args['snapshot'])) {
            $stage->replaceWorkingSnapshot($args['snapshot']);
        }

        $result = $stage->commit();

        return [
            ...$result->toArray(),
            'stageId' => $stage->id(),
            'status' => $stage->status(),
            'publishRequired' => true,
        ];
    }
}
