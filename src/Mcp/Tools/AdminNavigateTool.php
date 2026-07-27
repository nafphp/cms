<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;

final class AdminNavigateTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'cms_admin_navigate';
    }

    public function description(): string
    {
        return 'Navigates the current admin UI to a safe registered NixCMS admin area such as PageBuilder, articles, forms, themes, media, or settings.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('target', Schema::string()->description('Admin target area.')->enum([
                'dashboard',
                'pages',
                'pageBuilder',
                'articles',
                'articleEditor',
                'forms',
                'formBuilder',
                'themes',
                'themeEditor',
                'media',
                'settings',
                'aiSettings',
            ]))
            ->prop('id', Schema::integer()->description('Optional item id for edit screens.')->min(1))
            ->required('target')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function requiredScopes(): array
    {
        return ['admin:navigate'];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $target = trim((string) ($args['target'] ?? ''));
        $id = (int) ($args['id'] ?? 0);
        $url = $this->url($target, $id);

        return [
            'success' => true,
            'target' => $target,
            'url' => $url,
            'uiActions' => [
                [
                    'action' => 'admin.navigate',
                    'payload' => [
                        'url' => $url,
                    ],
                ],
            ],
        ];
    }

    private function url(string $target, int $id): string
    {
        return match ($target) {
            'dashboard' => '/admin',
            'pages' => '/admin/pages',
            'pageBuilder' => $id > 0 ? '/admin/pages/create?id=' . $id : '/admin/pages/create',
            'articles' => '/admin/articles',
            'articleEditor' => $id > 0 ? '/admin/articles/' . $id . '/edit' : '/admin/articles/create',
            'forms' => '/admin/forms',
            'formBuilder' => $id > 0 ? '/admin/forms/' . $id . '/edit' : '/admin/forms/create',
            'themes' => '/admin/themes',
            'themeEditor' => $id > 0 ? '/admin/themes/' . $id . '/edit' : '/admin/themes',
            'media' => '/admin/media',
            'settings' => '/admin/settings',
            'aiSettings' => '/admin/settings/ai',
            default => throw new \InvalidArgumentException(sprintf('Unknown admin navigation target "%s".', $target)),
        };
    }
}
