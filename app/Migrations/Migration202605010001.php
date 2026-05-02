<?php

declare(strict_types=1);

namespace NixCMS\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605010001 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        // Superseded during early page-builder development.
        // The canonical pages schema is created by Migration202605020003.
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS pages');
    }
}
