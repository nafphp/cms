<?php

declare(strict_types=1);

namespace NixPHP\CMS\Migrations;

use NixPHP\CMS\Enums\Permission;
use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605020002 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));

        $permissions = [
            Permission::PAGES_VIEW,
            Permission::PAGES_CREATE,
            Permission::PAGES_EDIT,
            Permission::PAGES_DELETE,
        ];

        $insertSql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO permissions (name) VALUES (:name)'
            : 'INSERT IGNORE INTO permissions (name) VALUES (:name)';
        $insert = $connection->prepare($insertSql);

        foreach ($permissions as $permission) {
            $insert->execute(['name' => $permission->value]);
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
        $link = $connection->prepare($linkSql);

        foreach ($permissions as $permission) {
            $link->execute([
                'role_id' => $adminRoleId,
                'name' => $permission->value,
            ]);
        }
    }

    public function down(PDO $connection): void
    {
    }
}
