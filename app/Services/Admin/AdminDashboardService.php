<?php

declare(strict_types=1);

namespace Cms\Services\Admin;

use function NixPHP\app;

final class AdminDashboardService
{
    public function summary(): array
    {
        return [
            'articles' => 0,
            'published_articles' => 0,
            'review_queue' => 0,
            'users' => 0,
            'plugins' => array_keys(app()->getPlugins()),
            'connectors' => [
                [
                    'group' => 'Core',
                    'label' => 'NixPHP plugins',
                    'status' => 'ready',
                    'tone' => 'success',
                    'detail' => count(app()->getPlugins()) . ' loaded',
                ],
                [
                    'group' => 'AI',
                    'label' => 'MCP server',
                    'status' => 'protected',
                    'tone' => 'warning',
                    'detail' => '/mcp requires bearer token auth',
                ],
            ],
        ];
    }
}
