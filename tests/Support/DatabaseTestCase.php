<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;
use Naf\Database\Core\Database;
use Naf\ORM\Core\EntityManager;
use Naf\ORM\Repository\RepositoryFactory;
use PDO;

use function Naf\app;

abstract class DatabaseTestCase extends NafTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        app()->container()->set(Database::class, new Database(
            ['driver' => 'sqlite', 'database' => ':memory:'],
            fn(): PDO => $this->pdo,
        ));

        // Repositories that reach for a sibling through repo() resolve it from
        // the container. Point that at the same in-memory connection so those
        // paths are testable at all.
        $entityManager = new EntityManager($this->pdo);
        app()->container()->set(EntityManager::class, $entityManager);
        app()->container()->set(
            RepositoryFactory::class,
            new RepositoryFactory($this->pdo, $entityManager),
        );
    }

    protected function createSchema(string ...$statements): void
    {
        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }
    }

    protected function tearDown(): void
    {
        // Restore the test-suite default: plain unit tests must not inherit the
        // previous test's in-memory database through the global container.
        app()->container()->set(Database::class, static fn(): ?Database => null);
        parent::tearDown();
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

        $columns      = array_keys($values);
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

        throw new InvalidArgumentException(sprintf('Unsafe SQL identifier "%s".', $identifier));
    }
}
