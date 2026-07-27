<?php

declare(strict_types=1);

namespace NixPHP\CMS\Migrations;

use NixPHP\CMS\Enums\Permission;
use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605020004 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType = $driver === 'sqlite' ? 'TEXT' : 'JSON';

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS forms (
                {$idColumn},
                identifier VARCHAR(120) NOT NULL,
                title VARCHAR(190) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                fields_json {$jsonType} NOT NULL,
                settings_json {$jsonType} NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_forms_identifier UNIQUE (identifier)
            )
        SQL);

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS form_submissions (
                {$idColumn},
                form_id INT NOT NULL,
                payload_json {$jsonType} NOT NULL,
                meta_json {$jsonType} NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        if ($driver === 'sqlite') {
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_forms_status ON forms (status)');
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_form_submissions_form ON form_submissions (form_id)');
        } else {
            $connection->exec('CREATE INDEX idx_forms_status ON forms (status)');
            $connection->exec('CREATE INDEX idx_form_submissions_form ON form_submissions (form_id)');
        }

        $this->seedPermissions($connection, $driver);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS form_submissions');
        $connection->exec('DROP TABLE IF EXISTS forms');
    }

    private function seedPermissions(PDO $connection, string $driver): void
    {
        $permissions = [
            Permission::FORMS_VIEW,
            Permission::FORMS_CREATE,
            Permission::FORMS_EDIT,
            Permission::FORMS_DELETE,
            Permission::FORMS_SUBMISSIONS_VIEW,
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
    }
}
