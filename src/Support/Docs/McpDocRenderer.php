<?php

declare(strict_types=1);

namespace src\Support\Docs;

use src\Support\Docs\DocBlockRenderer;
use src\Support\Docs\MarkdownTable;
use function NixPHP\MCP\tool;

final class McpDocRenderer implements DocBlockRenderer
{
    public function name(): string
    {
        return 'mcp';
    }

    public function render(): string
    {
        if (!function_exists('NixPHP\MCP\tool')) {
            return '_MCP support is not installed._';
        }

        try {
            $definitions = tool()->definitions();
        } catch (\Throwable $e) {
            return '_No MCP tools registered._';
        }

        usort($definitions, static fn(array $a, array $b): int => (string) ($a['name'] ?? '') <=> (string) ($b['name'] ?? ''));

        return MarkdownTable::render(
            ['Tool', 'Description'],
            array_map(
                static fn(array $definition): array => [
                    '`' . (string) ($definition['name'] ?? '') . '`',
                    (string) ($definition['description'] ?? ''),
                ],
                $definitions
            )
        );
    }
}
