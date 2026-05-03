<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixCMS\Enums\ArticleStatus;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PDO;
use Throwable;
use function NixPHP\Database\database;

final class ArticleStatusCountsTool implements ToolInterface, ScopedToolInterface
{
    public function __construct(private ?PDO $connection = null) {}

    public function name(): string
    {
        return 'cms_article_status_counts';
    }

    public function description(): string
    {
        return 'Returns latest article-version workflow status counts, optionally filtered by language.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('language', Schema::string()->default('all'))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['articles:read'];
    }

    public function handle(array $args): array
    {
        $language = $this->normalizeLanguage($args['language'] ?? 'all');
        $statusValues = array_map(static fn(ArticleStatus $status): string => $status->value, ArticleStatus::cases());
        $counts = array_fill_keys($statusValues, 0);
        $connection = $this->connection();

        if (!$connection instanceof PDO || !$this->tableExists($connection, 'article_versions')) {
            return [
                'metric' => 'latest_article_versions_by_status',
                'language' => $language,
                'status_values' => $statusValues,
                'counts' => $counts,
                'total' => 0,
                'database_available' => false,
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ];
        }

        $rows = $this->fetchStatusRows($connection, $language);

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!array_key_exists($status, $counts)) {
                continue;
            }

            $counts[$status] = (int) ($row['total'] ?? 0);
        }

        return [
            'metric' => 'latest_article_versions_by_status',
            'language' => $language,
            'status_values' => $statusValues,
            'counts' => $counts,
            'total' => array_sum($counts),
            'database_available' => true,
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function normalizeLanguage(mixed $language): string
    {
        $normalized = strtolower(trim((string) $language));

        return $normalized === '' ? 'all' : $normalized;
    }

    /**
     * @return array<int, array{status:string,total:int}>
     */
    private function fetchStatusRows(PDO $connection, string $language): array
    {
        $languageFilter = $language !== 'all';
        $latestWhere = $languageFilter ? 'WHERE language = :language_latest' : '';
        $outerWhere = $languageFilter ? 'WHERE av.language = :language_filter' : '';

        $sql = <<<SQL
            SELECT av.status, COUNT(*) AS total
            FROM article_versions av
            INNER JOIN (
                SELECT article_id, language, MAX(version) AS latest_version
                FROM article_versions
                {$latestWhere}
                GROUP BY article_id, language
            ) latest ON latest.article_id = av.article_id
                AND latest.language = av.language
                AND latest.latest_version = av.version
            {$outerWhere}
            GROUP BY av.status
        SQL;

        $statement = $connection->prepare($sql);
        $parameters = [];
        if ($languageFilter) {
            $parameters = [
                'language_latest' => $language,
                'language_filter' => $language,
            ];
        }

        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
