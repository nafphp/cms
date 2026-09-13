<?php

declare(strict_types=1);

namespace ExampleVendor\ReportPlugin;

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;
use Naf\Database\Support\MigrationRegistry;
use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;
use Psr\Http\Message\ResponseInterface;
use function Naf\CMS\cms;
use function Naf\json;
use function Naf\MCP\tool;
use function Naf\View\view;

function register(string $root): void
{
    cms()->pages()->add(
        Page::create('example_plugin.reports')
            ->setPath('/admin/example-plugin/reports')
            ->setTitle('Plugin reports')
            ->setLayout('admin')
            ->requirePermission(Permission::SYSTEM_READ)
            ->setContent(static fn(): string => view('example-plugin.report')),
        replace: true,
    );

    cms()->api()->add(
        ApiEndpoint::get('api.example_plugin.reports', '/api/admin/example-plugin/reports')
            ->requirePermission(Permission::SYSTEM_READ)
            ->setHandler(ReportApiHandler::class),
        replace: true,
    );

    MigrationRegistry::addPath($root . '/src/Migrations');
    tool()->register(new ReportSummaryTool());
}

final class ReportApiHandler
{
    public function __invoke(): ResponseInterface
    {
        return json(['reports' => 0]);
    }
}

final class ReportSummaryTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    public function name(): string { return 'cms_example_plugin_report_summary'; }
    public function description(): string { return 'Returns the current report summary.'; }
    public function inputSchema(): array { return Schema::object()->additionalProperties(false)->toArray(); }
    public function requiredScopes(): array { return ['system:read']; }
    public function handle(array $args): array { return ['reports' => 0]; }
    public function localAiMetadata(): array
    {
        return [
            'title' => 'Report summary',
            'routingText' => 'Read the installed report plugin summary.',
            'examples' => ['How many reports are available?'],
            'capabilities' => ['reports.read'],
            'mode' => 'read',
            'risk' => 'read',
            'autoRun' => true,
        ];
    }
}
