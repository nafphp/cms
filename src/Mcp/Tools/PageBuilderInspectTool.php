<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;

final class PageBuilderInspectTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_pagebuilder_inspect';
    }

    public function description(): string
    {
        return 'Starts the PageBuilder show mode so the user can click a visible block or nested element and return structured read-only information about it.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('hint', Schema::string()->description('Optional short instruction shown while the user clicks an element.'))
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function requiredScopes(): array
    {
        return ['pages:read'];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $hint = trim((string) ($args['hint'] ?? ''));

        return [
            'success' => true,
            'message' => 'Zeigemodus gestartet. Klick im PageBuilder auf einen Block oder ein Element.',
            'uiActions' => [
                [
                    'action' => 'pagebuilder.inspectElement',
                    'payload' => [
                        'hint' => $hint,
                    ],
                ],
            ],
        ];
    }
}
