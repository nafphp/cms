<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\CMS\Contracts\LocalAiToolMetadataInterface;
use NixPHP\CMS\Enums\ToolRisk;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PDO;
use Throwable;
use function NixPHP\Database\database;

final class ContentOverviewTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;
    public function __construct(private ?PDO $connection = null) {}

    public function name(): string
    {
        return 'cms_content_overview';
    }

    public function description(): string
    {
        return 'Returns high-level CMS content counts across articles, taxonomy, pages, media, users, and audit entries.';
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
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'counts' => [],
                'article_status_counts' => [],
                'page_status_counts' => [],
                'language_counts' => [],
            ];
        }

        try {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => true,
                'counts' => [
                    'articles' => $this->countTable($connection, 'articles'),
                    'latest_article_versions' => $this->latestArticleVersionCount($connection),
                    'article_versions' => $this->countTable($connection, 'article_versions'),
                    'categories' => $this->countTable($connection, 'categories'),
                    'tags' => $this->countTable($connection, 'tags'),
                    'pages' => $this->countTable($connection, 'pages'),
                    'media' => $this->countTable($connection, 'media'),
                    'users' => $this->countTable($connection, 'users'),
                    'audit_entries' => $this->countTable($connection, 'audit_log'),
                ],
                'article_status_counts' => $this->articleStatusCounts($connection),
                'page_status_counts' => $this->groupCount($connection, 'pages', 'status'),
                'language_counts' => [
                    'articles' => $this->groupCount($connection, 'article_versions', 'language'),
                    'pages' => $this->groupCount($connection, 'pages', 'language'),
                ],
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'error' => $throwable->getMessage(),
                'counts' => [],
                'article_status_counts' => [],
                'page_status_counts' => [],
                'language_counts' => [],
            ];
        }
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

    private function countTable(PDO $connection, string $table): int
    {
        if (!$this->tableExists($connection, $table)) {
            return 0;
        }

        return (int) $connection->query(sprintf('SELECT COUNT(*) FROM %s', $table))->fetchColumn();
    }

    private function latestArticleVersionCount(PDO $connection): int
    {
        if (!$this->tableExists($connection, 'article_versions')) {
            return 0;
        }

        return (int) $connection->query(<<<SQL
            SELECT COUNT(*)
            FROM article_versions av
            INNER JOIN (
                SELECT article_id, language, MAX(version) AS latest_version
                FROM article_versions
                GROUP BY article_id, language
            ) latest ON latest.article_id = av.article_id
                AND latest.language = av.language
                AND latest.latest_version = av.version
        SQL)->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    private function articleStatusCounts(PDO $connection): array
    {
        if (!$this->tableExists($connection, 'article_versions')) {
            return [];
        }

        $statement = $connection->query(<<<SQL
            SELECT av.status, COUNT(*) AS total
            FROM article_versions av
            INNER JOIN (
                SELECT article_id, language, MAX(version) AS latest_version
                FROM article_versions
                GROUP BY article_id, language
            ) latest ON latest.article_id = av.article_id
                AND latest.language = av.language
                AND latest.latest_version = av.version
            GROUP BY av.status
            ORDER BY av.status ASC
        SQL);

        return $this->rowsToMap($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], 'status');
    }

    /**
     * @return array<string, int>
     */
    private function groupCount(PDO $connection, string $table, string $column): array
    {
        if (!$this->tableExists($connection, $table)) {
            return [];
        }

        $statement = $connection->query(sprintf(
            'SELECT %s AS label, COUNT(*) AS total FROM %s GROUP BY %s ORDER BY %s ASC',
            $column,
            $table,
            $column,
            $column
        ));

        return $this->rowsToMap($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], 'label');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function rowsToMap(array $rows, string $labelKey): array
    {
        $map = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row[$labelKey] ?? ''));
            $map[$label !== '' ? $label : 'default'] = (int) ($row['total'] ?? 0);
        }

        return $map;
    }

    private function tableExists(PDO $connection, string $table): bool
    {
        try {
            $connection->query(sprintf('SELECT 1 FROM %s LIMIT 1', $table));
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
