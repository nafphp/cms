<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use function NixPHP\app;
use function NixCMS\cms;

final class PluginOverviewTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_plugin_overview';
    }

    public function description(): string
    {
        return 'Returns loaded NixPHP plugins and registry counts relevant to NixCMS extensions.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('include_paths', Schema::boolean()->default(false))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $includePaths = filter_var($args['include_paths'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $plugins = [];

        foreach (app()->getPlugins() as $package => $plugin) {
            $resources = [
                'config_paths' => $plugin->getConfigPaths(),
                'route_files' => $plugin->getRouteFiles(),
                'view_paths' => $plugin->getViewPaths(),
                'function_files' => $plugin->getFunctionsFiles(),
                'view_helper_files' => $plugin->getViewHelpersFiles(),
            ];

            $entry = [
                'package' => (string) $package,
                'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : null,
                'resources' => array_map('count', $resources),
            ];

            if ($includePaths) {
                $entry['paths'] = array_map(
                    fn(array $paths): array => array_map($this->relativePath(...), $paths),
                    $resources
                );
            }

            $plugins[] = $entry;
        }

        return [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'total' => count($plugins),
            'plugins' => $plugins,
            'registries' => [
                'pages' => count(cms()->pages()->all()),
                'api_endpoints' => count(cms()->api()->all()),
                'content_types' => count(cms()->contentTypes()->all()),
                'block_types' => count(cms()->blockTypes()->all()),
                'block_views' => count(cms()->blocks()->all()),
            ],
        ];
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim((string) app()->getBasePath(), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            return substr($path, strlen($basePath) + 1);
        }

        return $path;
    }
}
