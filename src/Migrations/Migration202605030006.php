<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\CMS\Enums\Permission;
use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202605030006 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver   = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType     = $driver === 'sqlite' ? 'TEXT' : 'JSON';
        $uniqueSyntax = $driver === 'sqlite' ? 'IF NOT EXISTS ' : '';

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS themes (
                {$idColumn},
                identifier VARCHAR(120) NOT NULL,
                label VARCHAR(190) NOT NULL,
                surfaces_json {$jsonType} NOT NULL,
                css TEXT NOT NULL,
                js TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_themes_identifier UNIQUE (identifier)
            )
        SQL);

        $connection->exec("CREATE INDEX {$uniqueSyntax}idx_themes_label ON themes (label)");
        $this->seedPermissions($connection, $driver);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS themes');
    }

    private function seedPermissions(PDO $connection, string $driver): void
    {
        $permissions = [
            Permission::THEMES_VIEW,
            Permission::THEMES_CREATE,
            Permission::THEMES_EDIT,
            Permission::THEMES_DELETE,
        ];

        $insert = $connection->prepare($driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO permissions (name) VALUES (:name)'
            : 'INSERT IGNORE INTO permissions (name) VALUES (:name)');

        foreach ($permissions as $permission) {
            $insert->execute(['name' => $permission->value]);
        }

        $adminRoleId = (int) ($connection->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn() ?: 0);
        if ($adminRoleId <= 0) {
            return;
        }

        $link = $connection->prepare($driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO role_permissions (role_id, permission_id) SELECT :role_id, id FROM permissions WHERE name = :name'
            : 'INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT :role_id, id FROM permissions WHERE name = :name');

        foreach ($permissions as $permission) {
            $link->execute([
                'role_id' => $adminRoleId,
                'name'    => $permission->value,
            ]);
        }
    }
}
