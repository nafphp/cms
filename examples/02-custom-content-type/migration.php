<?php

declare(strict_types=1);

namespace Naf\CMS\Examples\CustomContentType;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class ExampleCreateProductsTable extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS example_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS example_products');
    }
}
