<?php

declare(strict_types=1);

namespace src\Migrations;

use src\Enums\Permission;
use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605020001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType = $driver === 'sqlite' ? 'TEXT' : 'JSON';

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS navigations (
                {$idColumn},
                identifier VARCHAR(120) NOT NULL,
                title VARCHAR(190) NOT NULL,
                scope VARCHAR(40) NOT NULL DEFAULT 'frontend',
                language VARCHAR(8) NOT NULL DEFAULT '',
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                items_json {$jsonType} NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_navigations_identifier_scope_language UNIQUE (identifier, scope, language)
            )
        SQL);

        if ($driver === 'sqlite') {
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_navigations_scope ON navigations (scope)');
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_navigations_status ON navigations (status)');
        } else {
            if (!$this->indexExists($connection, 'navigations', 'idx_navigations_scope')) {
                $connection->exec('CREATE INDEX idx_navigations_scope ON navigations (scope)');
            }
            if (!$this->indexExists($connection, 'navigations', 'idx_navigations_status')) {
                $connection->exec('CREATE INDEX idx_navigations_status ON navigations (status)');
            }
        }

        $this->seedPermissions($connection, $driver);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS navigations');
    }

    private function seedPermissions(PDO $connection, string $driver): void
    {
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO permissions (name) VALUES (:name)'
            : 'INSERT IGNORE INTO permissions (name) VALUES (:name)';
        $statement = $connection->prepare($sql);

        $permissions = [
            Permission::NAVIGATION_VIEW,
            Permission::NAVIGATION_CREATE,
            Permission::NAVIGATION_EDIT,
            Permission::NAVIGATION_DELETE,
        ];

        foreach ($permissions as $permission) {
            $statement->execute(['name' => $permission->value]);
        }

        $adminRoleId = (int) ($connection->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn() ?: 0);
        if ($adminRoleId <= 0) {
            return;
        }

        $linkSql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO role_permissions (role_id, permission_id)
               SELECT :role_id, id FROM permissions WHERE name = :name'
            : 'INSERT IGNORE INTO role_permissions (role_id, permission_id)
               SELECT :role_id, id FROM permissions WHERE name = :name';
        $linkStatement = $connection->prepare($linkSql);

        foreach ($permissions as $permission) {
            $linkStatement->execute([
                'role_id' => $adminRoleId,
                'name' => $permission->value,
            ]);
        }
    }

    private function indexExists(PDO $connection, string $table, string $index): bool
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new \InvalidArgumentException(sprintf('Unsafe table name "%s".', $table));
        }

        $statement = $connection->query(sprintf(
            'SHOW INDEX FROM `%s` WHERE Key_name = %s',
            $table,
            $connection->quote($index),
        ));

        return $statement !== false && $statement->fetch(PDO::FETCH_ASSOC) !== false;
    }
}
