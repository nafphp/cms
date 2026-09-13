<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\CMS\Enums\Permission;
use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202607210001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec("DELETE FROM cms_settings WHERE setting_key = 'ai.api_key'");

        $insert = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT OR IGNORE'
            : 'INSERT IGNORE';
        $permission = $connection->prepare($insert . ' INTO permissions (name) VALUES (:name)');

        foreach ([Permission::AI_INSTRUCTIONS_MANAGE, Permission::THEMES_CODE_MANAGE] as $case) {
            $permission->execute(['name' => $case->value]);
        }

        $connection->exec(<<<SQL
            {$insert} INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r
            CROSS JOIN permissions p
            WHERE r.name = 'admin'
              AND p.name IN ('ai.instructions.manage', 'themes.code.manage')
        SQL);
    }

    public function down(PDO $connection): void
    {
        $connection->exec(<<<SQL
            DELETE FROM role_permissions
            WHERE permission_id IN (
                SELECT id FROM permissions
                WHERE name IN ('ai.instructions.manage', 'themes.code.manage')
            )
        SQL);
        $connection->exec("DELETE FROM permissions WHERE name IN ('ai.instructions.manage', 'themes.code.manage')");
    }
}
