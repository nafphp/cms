<?php

declare(strict_types=1);

namespace NixCMS\Services\Content;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Localization\LanguageRegistry;
use PDO;
use Throwable;
use function NixPHP\Database\database;

final class ArticleCatalogService
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    public function overview(): array
    {
        return [
            'statuses' => array_map(static fn(ArticleStatus $status) => $status->value, ArticleStatus::cases()),
            'languages' => (new LanguageRegistry())->enabled(),
            'articles' => $this->articles(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function articles(): array
    {
        try {
            $connection = $this->connection();
        } catch (Throwable) {
            return [];
        }

        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $rows = $connection
                ->query(<<<SQL
                    SELECT
                        a.id AS article_id,
                        a.createdAt AS article_created_at,
                        u.username AS author_username,
                        u.firstname AS author_firstname,
                        u.lastname AS author_lastname,
                        av.id AS version_id,
                        av.language,
                        av.version,
                        av.status,
                        av.slug,
                        av.title,
                        av.publishedAt,
                        av.scheduledAt,
                        av.createdAt AS version_created_at
                    FROM articles a
                    LEFT JOIN users u ON u.id = a.author
                    LEFT JOIN article_versions av ON av.article_id = a.id
                    ORDER BY a.id DESC, av.language ASC, av.version DESC
                SQL)
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }

        return $this->shapeArticleRows($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function shapeArticleRows(array $rows): array
    {
        $articles = [];

        foreach ($rows as $row) {
            $articleId = (int) ($row['article_id'] ?? 0);
            if ($articleId <= 0) {
                continue;
            }

            $articles[$articleId] ??= [
                'id' => $articleId,
                'title' => '-',
                'slug' => '-',
                'author' => $this->authorName($row),
                'publishedAt' => '',
                'lastVersion' => 0,
                'status' => '-',
                'createdAt' => (string) ($row['article_created_at'] ?? ''),
                'versions' => [],
            ];

            $versionId = (int) ($row['version_id'] ?? 0);
            if ($versionId <= 0) {
                continue;
            }

            $version = [
                'articleVersionId' => $versionId,
                'language' => strtolower(trim((string) ($row['language'] ?? ''))),
                'version' => (int) ($row['version'] ?? 0),
                'status' => strtolower(trim((string) ($row['status'] ?? ''))),
                'slug' => (string) ($row['slug'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'publishedAt' => (string) (($row['publishedAt'] ?? '') ?: ($row['scheduledAt'] ?? '')),
                'createdAt' => (string) ($row['version_created_at'] ?? ''),
            ];

            $articles[$articleId]['versions'][] = $version;

            if ($this->isBetterSummaryVersion($version, $articles[$articleId])) {
                $articles[$articleId]['title'] = $version['title'] !== '' ? $version['title'] : '-';
                $articles[$articleId]['slug'] = $version['slug'] !== '' ? $version['slug'] : '-';
                $articles[$articleId]['publishedAt'] = $version['publishedAt'];
                $articles[$articleId]['lastVersion'] = $version['version'];
                $articles[$articleId]['status'] = $version['status'] !== '' ? $version['status'] : '-';
            }
        }

        return array_values($articles);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function authorName(array $row): string
    {
        $name = trim((string) ($row['author_firstname'] ?? '') . ' ' . (string) ($row['author_lastname'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($row['author_username'] ?? ''));
        return $username !== '' ? $username : '-';
    }

    /**
     * @param array<string, mixed> $version
     * @param array<string, mixed> $article
     */
    private function isBetterSummaryVersion(array $version, array $article): bool
    {
        $currentVersion = (int) ($article['lastVersion'] ?? 0);
        $candidateVersion = (int) ($version['version'] ?? 0);

        if ($currentVersion === 0) {
            return true;
        }

        $currentStatus = (string) ($article['status'] ?? '');
        $candidateStatus = (string) ($version['status'] ?? '');

        if ($candidateStatus === ArticleStatus::PUBLISHED->value && $currentStatus !== ArticleStatus::PUBLISHED->value) {
            return true;
        }

        return $candidateVersion > $currentVersion;
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $connection = database();
        } catch (Throwable) {
            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }
}
