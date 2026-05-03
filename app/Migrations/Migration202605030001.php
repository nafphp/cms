<?php

declare(strict_types=1);

namespace NixCMS\Migrations;

use NixCMS\Enums\Permission;
use NixCMS\Enums\Role;
use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605030001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType = $driver === 'sqlite' ? 'TEXT' : 'JSON';
        $integerType = $driver === 'sqlite' ? 'INTEGER' : 'INT';
        $bigIntegerType = $driver === 'sqlite' ? 'INTEGER' : 'BIGINT';

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS media (
                {$idColumn},
                public_id VARCHAR(40) NOT NULL,
                disk VARCHAR(32) NOT NULL DEFAULT 'public',
                visibility VARCHAR(24) NOT NULL DEFAULT 'public',
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(512) NOT NULL,
                file_path VARCHAR(512) NOT NULL,
                public_url VARCHAR(512) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                extension VARCHAR(24) NOT NULL,
                size_bytes {$bigIntegerType} NOT NULL DEFAULT 0,
                width {$integerType} NULL,
                height {$integerType} NULL,
                alt_text VARCHAR(255) NULL,
                uploaded_by {$integerType} NULL,
                metadata_json {$jsonType} NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_media_public_id UNIQUE (public_id),
                CONSTRAINT uq_media_public_url UNIQUE (public_url),
                CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by)
                    REFERENCES users (id) ON DELETE SET NULL
            )
        SQL);

        if ($driver === 'sqlite') {
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_media_created_at ON media (created_at)');
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_media_mime_type ON media (mime_type)');
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_media_visibility ON media (visibility)');
        } else {
            $connection->exec('CREATE INDEX idx_media_created_at ON media (created_at)');
            $connection->exec('CREATE INDEX idx_media_mime_type ON media (mime_type)');
            $connection->exec('CREATE INDEX idx_media_visibility ON media (visibility)');
        }

        $this->seedPermissions($connection, $driver);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS media');
    }

    private function seedPermissions(PDO $connection, string $driver): void
    {
        $permissions = [
            Permission::MEDIA_VIEW,
            Permission::MEDIA_UPLOAD,
            Permission::MEDIA_EDIT,
            Permission::MEDIA_DELETE,
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
                'name' => $permission->value,
            ]);
        }

        $rolePermissions = [
            Role::EDITOR->value => [
                Permission::MEDIA_VIEW,
                Permission::MEDIA_UPLOAD,
                Permission::MEDIA_EDIT,
                Permission::MEDIA_DELETE,
            ],
            Role::AUTHOR->value => [
                Permission::MEDIA_VIEW,
                Permission::MEDIA_UPLOAD,
            ],
            Role::REVIEWER->value => [
                Permission::MEDIA_VIEW,
            ],
            Role::TRANSLATOR->value => [
                Permission::MEDIA_VIEW,
            ],
        ];

        $roleStatement = $connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        foreach ($rolePermissions as $roleName => $grantedPermissions) {
            $roleStatement->execute(['name' => $roleName]);
            $roleId = (int) ($roleStatement->fetchColumn() ?: 0);
            if ($roleId <= 0) {
                continue;
            }

            foreach ($grantedPermissions as $permission) {
                $link->execute([
                    'role_id' => $roleId,
                    'name' => $permission->value,
                ]);
            }
        }
    }
}
