<?php

declare(strict_types=1);

namespace NixCMS\Services\Admin;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Repositories\ArticleRepository;
use NixCMS\Repositories\UserRepository;
use function NixPHP\app;
use function NixPHP\ORM\repo;

final class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $articles = repo(ArticleRepository::class);
        $users = repo(UserRepository::class);
        $plugins = array_keys(app()->getPlugins());

        return [
            'articles' => $articles->countAll(),
            'published_articles' => $articles->countVersionsByStatus(ArticleStatus::PUBLISHED),
            'review_queue' => $articles->countDraftReviewQueue(),
            'users' => $users->countAll(),
            'plugins' => $plugins,
            'connectors' => [
                [
                    'group' => 'Core',
                    'label' => 'NixPHP plugins',
                    'status' => 'ready',
                    'tone' => 'success',
                    'detail' => count($plugins) . ' loaded',
                ],
                [
                    'group' => 'AI',
                    'label' => 'MCP server',
                    'status' => app()->hasPlugin('nixphp/mcp') ? 'protected' : 'inactive',
                    'tone' => app()->hasPlugin('nixphp/mcp') ? 'warning' : 'muted',
                    'detail' => app()->hasPlugin('nixphp/mcp')
                        ? '/mcp requires bearer token auth'
                        : 'nixphp/mcp plugin not loaded',
                ],
            ],
        ];
    }
}
