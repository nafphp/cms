<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202610150001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $insert     = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $permission = $connection->prepare($insert . ' INTO permissions (name) VALUES (:name)');
        $assignment = $connection->prepare($insert . " INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'admin' AND p.name = :name");

        foreach (['settings.manage', 'translations.manage'] as $name) {
            $permission->execute(['name' => $name]);
            $assignment->execute(['name' => $name]);
        }
    }

    public function down(PDO $connection): void
    {
        $connection->exec("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name IN ('settings.manage', 'translations.manage'))");
        $connection->exec("DELETE FROM permissions WHERE name IN ('settings.manage', 'translations.manage')");
    }
}
