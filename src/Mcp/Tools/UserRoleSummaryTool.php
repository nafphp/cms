<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use PDO;
use Throwable;
use function NixPHP\Database\database;

final class UserRoleSummaryTool implements ToolInterface, ScopedToolInterface
{
    public function __construct(private ?PDO $connection = null) {}

    public function name(): string
    {
        return 'cms_user_role_summary';
    }

    public function description(): string
    {
        return 'Returns user, role, permission, and assignment counts for the NixCMS RBAC model.';
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
        return ['users:read'];
    }

    public function handle(array $args): array
    {
        $connection = $this->connection();
        $requiredTables = ['users', 'roles', 'permissions', 'user_role', 'role_permissions'];

        if (!$connection instanceof PDO || !$this->tablesExist($connection, $requiredTables)) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'users' => 0,
                'roles' => 0,
                'permissions' => 0,
                'user_role_assignments' => 0,
                'role_permission_assignments' => 0,
                'roles_detail' => [],
                'permissions_by_area' => [],
            ];
        }

        return [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'database_available' => true,
            'users' => $this->countTable($connection, 'users'),
            'roles' => $this->countTable($connection, 'roles'),
            'permissions' => $this->countTable($connection, 'permissions'),
            'user_role_assignments' => $this->countTable($connection, 'user_role'),
            'role_permission_assignments' => $this->countTable($connection, 'role_permissions'),
            'roles_detail' => $this->roleDetails($connection),
            'permissions_by_area' => $this->permissionsByArea($connection),
        ];
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

    /**
     * @param array<int, string> $tables
     */
    private function tablesExist(PDO $connection, array $tables): bool
    {
        foreach ($tables as $table) {
            if (!$this->tableExists($connection, $table)) {
                return false;
            }
        }

        return true;
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

    private function countTable(PDO $connection, string $table): int
    {
        return (int) $connection->query(sprintf('SELECT COUNT(*) FROM %s', $table))->fetchColumn();
    }

    /**
     * @return array<int, array{name:string,user_count:int,permission_count:int}>
     */
    private function roleDetails(PDO $connection): array
    {
        $statement = $connection->query(<<<SQL
            SELECT
                r.name,
                COUNT(DISTINCT ur.user_id) AS user_count,
                COUNT(DISTINCT rp.permission_id) AS permission_count
            FROM roles r
            LEFT JOIN user_role ur ON ur.role_id = r.id
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            GROUP BY r.id, r.name
            ORDER BY r.name ASC
        SQL);

        return array_map(
            static fn(array $row): array => [
                'name' => (string) ($row['name'] ?? ''),
                'user_count' => (int) ($row['user_count'] ?? 0),
                'permission_count' => (int) ($row['permission_count'] ?? 0),
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * @return array<string, int>
     */
    private function permissionsByArea(PDO $connection): array
    {
        $statement = $connection->query('SELECT name FROM permissions ORDER BY name ASC');
        $areas = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) ?: [] as $permission) {
            $name = (string) $permission;
            $area = str_contains($name, '.') ? strstr($name, '.', true) : $name;
            $area = $area === false || $area === '' ? 'other' : $area;
            $areas[$area] = ($areas[$area] ?? 0) + 1;
        }

        ksort($areas);

        return $areas;
    }
}
