<?php

declare(strict_types=1);

namespace NixPHP\CMS\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605020003 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType = $driver === 'sqlite' ? 'TEXT' : 'JSON';

        $connection->exec(<<<SQL
            CREATE TABLE pages (
                {$idColumn},
                identifier VARCHAR(160) NOT NULL,
                path VARCHAR(255) NOT NULL,
                title VARCHAR(190) NOT NULL,
                language VARCHAR(8) NOT NULL DEFAULT '',
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                template_id VARCHAR(120) NOT NULL,
                blocks_json {$jsonType} NOT NULL,
                meta_json {$jsonType} NOT NULL,
                layout VARCHAR(120) NOT NULL DEFAULT 'layouts.page',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_pages_path_language UNIQUE (path, language)
            )
        SQL);

        if ($driver === 'sqlite') {
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_pages_status ON pages (status)');
        } else {
            $connection->exec('CREATE INDEX idx_pages_status ON pages (status)');
        }
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS pages');
    }

}
