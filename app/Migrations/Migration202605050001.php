<?php

declare(strict_types=1);

namespace NixCMS\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605050001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType = $driver === 'sqlite' ? 'TEXT' : 'JSON';
        $indexPrefix = $driver === 'sqlite' ? 'IF NOT EXISTS ' : '';

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS cms_stages (
                {$idColumn},
                target_type VARCHAR(80) NOT NULL,
                target_id VARCHAR(160) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'open',
                base_snapshot_json {$jsonType} NOT NULL,
                working_snapshot_json {$jsonType} NOT NULL,
                changes_json {$jsonType} NOT NULL,
                ui_actions_json {$jsonType} NOT NULL,
                warnings_json {$jsonType} NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                committed_at DATETIME NULL
            )
        SQL);

        $connection->exec("CREATE INDEX {$indexPrefix}idx_cms_stages_target ON cms_stages (target_type, target_id)");
        $connection->exec("CREATE INDEX {$indexPrefix}idx_cms_stages_status ON cms_stages (status)");
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS cms_stages');
    }
}
