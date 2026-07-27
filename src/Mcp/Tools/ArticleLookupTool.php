<?php

declare(strict_types=1);

namespace src\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PDO;
use Throwable;
use function NixPHP\Database\database;

final class ArticleLookupTool implements ToolInterface, ScopedToolInterface
{
    public function __construct(private ?PDO $connection = null) {}

    public function name(): string
    {
        return 'cms_article_lookup';
    }

    public function description(): string
    {
        return 'Finds latest article versions by title, slug, language, or status so later MCP actions can target an article id.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('query', Schema::string()->description('Optional title or slug search text.')->default(''))
            ->prop('language', Schema::string()->description('Language code or all.')->default('all'))
            ->prop('status', Schema::string()->description('Workflow status or all.')->default('all'))
            ->prop('limit', Schema::integer()->min(1)->max(50)->default(10))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['articles:read'];
    }

    public function handle(array $args): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'matches' => [],
            ];
        }

        $query = strtolower(trim((string) ($args['query'] ?? '')));
        $language = strtolower(trim((string) ($args['language'] ?? 'all'))) ?: 'all';
        $status = strtolower(trim((string) ($args['status'] ?? 'all'))) ?: 'all';
        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));

        try {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => true,
                'query' => $query,
                'language' => $language,
                'status' => $status,
                'matches' => $this->matches($connection, $query, $language, $status, $limit),
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'error' => $throwable->getMessage(),
                'matches' => [],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matches(PDO $connection, string $query, string $language, string $status, int $limit): array
    {
        $where = [];
        $params = [];

        if ($query !== '') {
            $where[] = '(LOWER(av.title) LIKE :query OR LOWER(av.slug) LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($language !== 'all') {
            $where[] = 'av.language = :language';
            $params['language'] = $language;
        }

        if ($status !== 'all') {
            $where[] = 'av.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $statement = $connection->prepare(<<<SQL
            SELECT
                av.article_id,
                av.id AS article_version_id,
                av.language,
                av.version,
                av.status,
                av.title,
                av.slug,
                av.teaser,
                av.createdAt AS created_at,
                c.name AS category
            FROM article_versions av
            INNER JOIN (
                SELECT article_id, language, MAX(version) AS latest_version
                FROM article_versions
                GROUP BY article_id, language
            ) latest ON latest.article_id = av.article_id
                AND latest.language = av.language
                AND latest.latest_version = av.version
            INNER JOIN articles a ON a.id = av.article_id
            LEFT JOIN categories c ON c.id = a.category_id
            {$whereSql}
            ORDER BY av.createdAt DESC, av.article_id DESC
            LIMIT {$limit}
        SQL);
        $statement->execute($params);

        return array_map(
            static fn(array $row): array => [
                'articleId' => (int) ($row['article_id'] ?? 0),
                'articleVersionId' => (int) ($row['article_version_id'] ?? 0),
                'language' => (string) ($row['language'] ?? ''),
                'version' => (int) ($row['version'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'teaser' => (string) ($row['teaser'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
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
