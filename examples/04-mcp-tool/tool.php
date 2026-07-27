<?php

declare(strict_types=1);

namespace NixPHP\CMS\Examples\McpTool;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use function NixPHP\MCP\tool;

final class ExamplePingTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_example_ping';
    }

    public function description(): string
    {
        return 'Returns a trivial pong response for testing MCP registration.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        return ['pong' => true];
    }
}

tool()->register(new ExamplePingTool());
