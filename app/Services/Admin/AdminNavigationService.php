<?php

declare(strict_types=1);

namespace Cms\Services\Admin;

use function NixPHP\config;
use function NixPHP\route;

final class AdminNavigationService
{
    public function items(): array
    {
        $coreItems = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'href' => route('admin.dashboard'),
                'routeNames' => ['admin.dashboard'],
                'icon' => 'stats',
            ],
            [
                'key' => 'articles',
                'label' => 'Articles',
                'href' => route('admin.articles'),
                'routeNames' => [
                    'admin.articles',
                    'admin.articles.create',
                    'admin.articles.edit',
                    'admin.articles.version.create',
                    'admin.articles.version.edit',
                    'admin.articles.translation.create',
                    'admin.articles.preview',
                ],
                'icon' => 'article',
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'href' => route('admin.users'),
                'routeNames' => ['admin.users', 'admin.users.create'],
                'icon' => 'user',
            ],
            [
                'key' => 'roles',
                'label' => 'Roles & Permissions',
                'href' => route('admin.roles'),
                'routeNames' => ['admin.roles'],
                'icon' => 'lock',
            ],
        ];

        $pluginItems = config('cms:admin:navigation', []);
        if (!is_array($pluginItems)) {
            return $coreItems;
        }

        return array_values(array_merge($coreItems, $pluginItems));
    }

    public function accentForPath(string $path): string
    {
        $colors = config('cms:admin:accent_colors', []);
        if (!is_array($colors)) {
            $colors = [];
        }

        $key = match (true) {
            $path === '/admin' => 'dashboard',
            str_starts_with($path, '/admin/articles') => 'articles',
            str_starts_with($path, '/admin/users') => 'users',
            str_starts_with($path, '/admin/roles') => 'roles',
            default => 'settings',
        };

        return (string) ($colors[$key] ?? '#64748b');
    }
}
