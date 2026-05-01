<?php

declare(strict_types=1);

namespace NixCMS\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605010002 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS cms_settings (
                {$idColumn},
                setting_key VARCHAR(160) NOT NULL,
                value TEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_cms_settings_key UNIQUE (setting_key)
            )
        SQL);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS cms_settings');
    }
}
