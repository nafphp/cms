<?php

declare(strict_types=1);

namespace Naf\CMS\Mcp\Tools;

use Naf\CMS\Contracts\LocalAiToolMetadataInterface;
use Naf\CMS\Enums\ToolRisk;

use Naf\MCP\Support\Schema;
use Naf\MCP\Tools\ScopedToolInterface;
use Naf\MCP\Tools\ToolInterface;
use PDO;
use Throwable;
use function Naf\Database\database;

final class PageOverviewTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;
    public function __construct(private ?PDO $connection = null) {}

    public function name(): string
    {
        return 'cms_page_overview';
    }

    public function description(): string
    {
        return 'Returns page counts by status, language, template, and optional recent pages.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('include_recent', Schema::boolean()->default(true))
            ->prop('status', Schema::string()->description('Page status or all.')->default('all'))
            ->prop('language', Schema::string()->description('Language code or all.')->default('all'))
            ->prop('limit', Schema::integer()->min(1)->max(50)->default(10))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['pages:read'];
    }

    public function handle(array $args): array
    {
        $connection = $this->connection();
        $includeRecent = filter_var($args['include_recent'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $status = strtolower(trim((string) ($args['status'] ?? 'all'))) ?: 'all';
        $language = strtolower(trim((string) ($args['language'] ?? 'all'))) ?: 'all';
        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));

        if (!$connection instanceof PDO || !$this->tableExists($connection, 'pages')) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'total' => 0,
                'status_counts' => [],
                'language_counts' => [],
                'template_counts' => [],
                'recent' => [],
            ];
        }

        try {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => true,
                'total' => $this->countPages($connection, $status, $language),
                'status_counts' => $this->groupCount($connection, 'status'),
                'language_counts' => $this->groupCount($connection, 'language'),
                'template_counts' => $this->groupCount($connection, 'template_id'),
                'recent' => $includeRecent ? $this->recentPages($connection, $status, $language, $limit) : [],
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'error' => $throwable->getMessage(),
                'total' => 0,
                'status_counts' => [],
                'language_counts' => [],
                'template_counts' => [],
                'recent' => [],
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

    private function countPages(PDO $connection, string $status, string $language): int
    {
        [$where, $params] = $this->filters($status, $language);
        $statement = $connection->prepare('SELECT COUNT(*) FROM pages ' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    private function groupCount(PDO $connection, string $column): array
    {
        $statement = $connection->query(sprintf(
            'SELECT %s AS label, COUNT(*) AS total FROM pages GROUP BY %s ORDER BY %s ASC',
            $column,
            $column,
            $column
        ));

        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $map[$label !== '' ? $label : 'default'] = (int) ($row['total'] ?? 0);
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPages(PDO $connection, string $status, string $language, int $limit): array
    {
        [$where, $params] = $this->filters($status, $language);
        $statement = $connection->prepare(<<<SQL
            SELECT id, identifier, path, title, language, status, template_id, layout, created_at, updated_at
            FROM pages
            {$where}
            ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
            LIMIT {$limit}
        SQL);
        $statement->execute($params);

        return array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'identifier' => (string) ($row['identifier'] ?? ''),
                'path' => (string) ($row['path'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'language' => (string) ($row['language'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'template_id' => (string) ($row['template_id'] ?? ''),
                'layout' => (string) ($row['layout'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * @return array{0:string,1:array<string, string>}
     */
    private function filters(string $status, string $language): array
    {
        $where = [];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($language !== 'all') {
            $where[] = 'language = :language';
            $params['language'] = $language;
        }

        return [$where !== [] ? 'WHERE ' . implode(' AND ', $where) : '', $params];
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
