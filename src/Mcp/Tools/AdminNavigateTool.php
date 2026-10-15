<?php

declare(strict_types=1);

namespace Naf\CMS\Mcp\Tools;

use InvalidArgumentException;
use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Enums\ToolRisk;
use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;

final class AdminNavigateTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK    = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;

    public function name(): string
    {
        return 'cms_admin_navigate';
    }

    public function description(): string
    {
        return 'Navigates the current admin UI to a safe registered NafCMS admin area such as PageBuilder, articles, forms, themes, media, users, roles, navigation, or settings.';
    }

    /**
     * @return array<string, mixed>
     */
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
                'users',
                'userEditor',
                'roles',
                'navigation',
                'navigationEditor',
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
        $id     = (int) ($args['id'] ?? 0);
        $url    = $this->url($target, $id);

        return [
            'success'   => true,
            'target'    => $target,
            'url'       => $url,
            'uiActions' => [
                [
                    'action'  => 'admin.navigate',
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
            'dashboard'        => '/admin',
            'pages'            => '/admin/pages',
            'pageBuilder'      => $id > 0 ? '/admin/pages/create?id=' . $id : '/admin/pages/create',
            'articles'         => '/admin/articles',
            'articleEditor'    => $id > 0 ? '/admin/articles/' . $id . '/edit' : '/admin/articles/create',
            'forms'            => '/admin/forms',
            'formBuilder'      => $id > 0 ? '/admin/forms/' . $id . '/edit' : '/admin/forms/create',
            'themes'           => '/admin/themes',
            'themeEditor'      => $id > 0 ? '/admin/themes/' . $id . '/edit' : '/admin/themes',
            'media'            => '/admin/media',
            'users'            => '/admin/users',
            'userEditor'       => $id > 0 ? '/admin/users/' . $id . '/edit' : '/admin/users/create',
            'roles'            => '/admin/roles',
            'navigation'       => '/admin/navigation',
            'navigationEditor' => $id > 0 ? '/admin/navigation/' . $id . '/edit' : '/admin/navigation/create',
            'settings'         => '/admin/settings',
            'aiSettings'       => '/admin/settings/ai',
            default            => throw new InvalidArgumentException(sprintf('Unknown admin navigation target "%s".', $target)),
        };
    }
}
