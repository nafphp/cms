<?php

declare(strict_types=1);

namespace NixCMS\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605010001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_key VARCHAR(120) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                path VARCHAR(240) NOT NULL,
                title VARCHAR(190) NOT NULL,
                layout VARCHAR(80) NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                requires_auth TINYINT(1) NOT NULL DEFAULT 0,
                required_role VARCHAR(80) NULL,
                required_permission VARCHAR(120) NULL,
                content_type VARCHAR(80) NULL,
                content_id INT NULL,
                meta_json JSON NOT NULL,
                meta_version INT NOT NULL DEFAULT 1,
                created_by INT NULL,
                updated_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_pages_page_key UNIQUE (page_key),
                CONSTRAINT uq_pages_path UNIQUE (path),
                CONSTRAINT fk_pages_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_pages_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_pages_slug (slug),
                INDEX idx_pages_status (status),
                INDEX idx_pages_required_permission (required_permission),
                INDEX idx_pages_required_role (required_role)
            )
        SQL);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS pages');
    }
}
