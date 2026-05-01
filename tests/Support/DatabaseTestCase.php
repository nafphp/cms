<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

abstract class DatabaseTestCase extends NixPHPTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    protected function createSchema(string ...$statements): void
    {
        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function insertRow(string $table, array $values): int
    {
        $this->assertIdentifier($table);

        foreach (array_keys($values) as $column) {
            $this->assertIdentifier((string) $column);
        }

        $columns = array_keys($values);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        ));
        $statement->execute($values);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1) {
            return;
        }

        throw new \InvalidArgumentException(sprintf('Unsafe SQL identifier "%s".', $identifier));
    }
}
