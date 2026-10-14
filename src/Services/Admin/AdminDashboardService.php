<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Admin;

use FilesystemIterator;
use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Repositories\ArticleRepository;
use Naf\CMS\Repositories\PermissionRepository;
use Naf\CMS\Repositories\RoleRepository;
use Naf\CMS\Repositories\UserRepository;
use Naf\CMS\Support\RuntimePaths;
use Naf\CMS\Visualization\ChartDataset;
use Naf\CMS\Visualization\ChartDefinition;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\config;
use function Naf\I18n\t;
use function Naf\MCP\tool;
use function Naf\ORM\repo;
use function Naf\route;

final class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $articles            = repo(ArticleRepository::class);
        $users               = repo(UserRepository::class);
        $plugins             = array_keys(app()->getPlugins());
        $language            = cms()->settings()->currentLanguage() ?: cms()->settings()->language();
        $statusOverview      = $articles->getArticleStatusOverview(null, $language);
        $articleCount        = $articles->countAll();
        $publishedCount      = $articles->countVersionsByStatus(ArticleStatus::PUBLISHED);
        $reviewQueueCount    = $articles->countDraftReviewQueue();
        $userCount           = $users->countAll();
        $roleCount           = count(repo(RoleRepository::class)->findAll());
        $permissionCount     = count(repo(PermissionRepository::class)->findAll());
        $toolCount           = $this->mcpToolCount();
        $registeredPageCount = count(cms()->pages()->all());
        $apiEndpointCount    = count(cms()->api()->all());
        $routeCount          = count(route()->all());
        $storageSizes        = $this->storageSizes();

        return [
            'articles'           => $articleCount,
            'published_articles' => $publishedCount,
            'review_queue'       => $reviewQueueCount,
            'users'              => $userCount,
            'plugins'            => $plugins,
            'charts'             => [
                'content_inventory' => $this->metricBars(
                    'dashboard-content-inventory',
                    t('Content inventory'),
                    t('Primary CMS records at a glance.'),
                    [
                        t('Articles')     => $articleCount,
                        t('Published')    => $publishedCount,
                        t('Review queue') => $reviewQueueCount,
                        t('Users')        => $userCount,
                    ],
                    ['#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6'],
                ),
                'article_workflow' => $this->metricBars(
                    'dashboard-article-workflow',
                    t('Article workflow'),
                    sprintf(t('Latest article versions for %s.'), strtoupper($language)),
                    $this->labelStatusCounts($statusOverview),
                    ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
                ),
                'user_security' => $this->metricBars(
                    'dashboard-user-security',
                    t('Users and permissions'),
                    t('Accounts, roles, and permission surface.'),
                    [
                        t('Users')       => $userCount,
                        t('Roles')       => $roleCount,
                        t('Permissions') => $permissionCount,
                    ],
                    ['#8b5cf6', '#06b6d4', '#14b8a6'],
                ),
                'storage_usage' => ChartDefinition::bar(
                    'dashboard-storage-usage',
                    [
                        t('Storage') => $storageSizes['storage_kb'],
                        t('Media')   => $storageSizes['media_kb'],
                        t('Public')  => $storageSizes['public_kb'],
                    ],
                    t('Storage footprint'),
                    t('Approximate directory usage in KB.'),
                    [
                        'datasetLabel' => t('KB'),
                        'height'       => 260,
                        'colors'       => ['#14b8a6'],
                    ],
                ),
            ],
            'platform_stats' => [
                [
                    'label'  => t('MCP tools'),
                    'value'  => $toolCount,
                    'detail' => t('Executable AI capabilities'),
                ],
                [
                    'label'  => t('Users'),
                    'value'  => $userCount,
                    'detail' => t('Registered accounts'),
                ],
                [
                    'label'  => t('Routes'),
                    'value'  => $routeCount,
                    'detail' => t('Registered HTTP routes'),
                ],
                [
                    'label'  => t('Pages'),
                    'value'  => $registeredPageCount,
                    'detail' => t('Registered CMS pages'),
                ],
                [
                    'label'  => t('API endpoints'),
                    'value'  => $apiEndpointCount,
                    'detail' => t('Registered JSON actions'),
                ],
                [
                    'label'  => t('NAF plugins'),
                    'value'  => count($plugins),
                    'detail' => t('Loaded framework plugins'),
                ],
            ],
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    private function labelStatusCounts(array $counts): array
    {
        $labels = [];
        foreach (ArticleStatus::cases() as $status) {
            $labels[t(ucfirst($status->value))] = (int) ($counts[$status->value] ?? 0);
        }

        return $labels;
    }

    /**
     * @param array<string, int> $values
     * @param array<int, string> $colors
     */
    private function metricBars(
        string $id,
        string $title,
        string $subtitle,
        array $values,
        array $colors,
    ): ChartDefinition {
        return ChartDefinition::make(
            $id,
            'metric-bars',
            array_keys($values),
            [ChartDataset::make(t('Count'), array_values($values))],
            $title,
            $subtitle,
            260,
            [
                'colors'  => $colors,
                'echarts' => [
                    'grid' => ['left' => 0, 'right' => 28, 'top' => 6, 'bottom' => 0, 'containLabel' => true],
                ],
            ],
        );
    }

    /**
     * @return array{storage_kb:int, media_kb:int, public_kb:int}
     */
    private function storageSizes(): array
    {
        return [
            'storage_kb' => $this->folderSizeKb(RuntimePaths::storageRoot()),
            'media_kb'   => $this->folderSizeKb((string) config('cms:media:storage_path', RuntimePaths::hostRoot() . '/storage/media')),
            'public_kb'  => $this->folderSizeKb(RuntimePaths::publicRoot()),
        ];
    }

    private function folderSizeKb(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $bytes    = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item instanceof SplFileInfo && $item->isFile()) {
                $bytes += max(0, $item->getSize());
            }
        }

        return max(1, (int) ceil($bytes / 1024));
    }

    private function mcpToolCount(): int
    {
        return count(tool()->definitions());
    }
}
