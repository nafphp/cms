<?php

declare(strict_types=1);

namespace NixCMS\Storage;

use InvalidArgumentException;
use NixCMS\Storage\Filter\ContainsFilter;
use NixCMS\Storage\Filter\EqualsFilter;
use NixCMS\Storage\Filter\FilterInterface;
use NixCMS\Storage\Filter\InFilter;
use NixCMS\Storage\Filter\NotEqualsFilter;
use NixCMS\Storage\Filter\RangeFilter;
use NixPHP\ORM\Core\EntityManager;
use NixPHP\ORM\Core\EntityInterface;
use PDO;
use function NixPHP\Database\database;
use function NixPHP\ORM\em;

/**
 * Generic typed CRUD over a single entity.
 *
 * NOT permission-aware. NOT auditing.
 * Use cms()->{contentType}() for domain operations that need either.
 *
 * Use Storage when:
 * - you need ad-hoc queries on entities without a custom Repository,
 * - you don't need joins or aggregates,
 * - the caller already enforces authorization.
 *
 * @template T of EntityInterface
 */
final class EntityStorage
{
    /**
     * @var T
     */
    private EntityInterface $model;

    /**
     * @param class-string<T> $modelClass
     */
    public function __construct(
        private string $modelClass,
        private ?PDO $connection = null,
        private ?EntityManager $entityManager = null,
    ) {
        if (!is_subclass_of($modelClass, EntityInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Storage model "%s" must implement %s.',
                $modelClass,
                EntityInterface::class,
            ));
        }

        $this->model = new $modelClass();
    }

    /**
     * @param array<string, mixed> $data
     * @return T
     */
    public function make(array $data = []): EntityInterface
    {
        /** @var T $entity */
        $entity = new $this->modelClass($this->filterWritableData($data, allowPrimaryKey: true));
        return $entity;
    }

    /**
     * @param array<string, mixed> $data
     * @return T
     * @see ContentApi for permission-aware and audited equivalents.
     */
    public function create(array $data): EntityInterface
    {
        return $this->save($this->make($data));
    }

    /**
     * @param T $entity
     * @return T
     * @see ContentApi for permission-aware and audited equivalents.
     */
    public function save(EntityInterface $entity): EntityInterface
    {
        if (!$entity instanceof $this->modelClass) {
            throw new InvalidArgumentException(sprintf(
                'Cannot save "%s" through storage for "%s".',
                $entity::class,
                $this->modelClass,
            ));
        }

        $this->withDeprecationsSuppressed(fn() => $this->entityManager()->save($entity));

        return $entity;
    }

    /**
     * @param array<string, mixed> $data
     * @return T|null
     * @see ContentApi for permission-aware and audited equivalents.
     */
    public function update(int|string $id, array $data): ?EntityInterface
    {
        $entity = $this->find($id);
        if (!$entity instanceof EntityInterface) {
            return null;
        }

        $this->apply($entity, $data);

        return $this->save($entity);
    }

    /**
     * @return T|null
     */
    public function find(int|string $id): ?EntityInterface
    {
        return $this->first(Criteria::create()->equals($this->model->getPrimaryKey(), $id));
    }

    /**
     * @see ContentApi for permission-aware and audited equivalents.
     */
    public function delete(int|string|EntityInterface $target): bool
    {
        $id = $target instanceof EntityInterface ? $target->getId() : $target;
        if ($id === '' || $id === null) {
            return false;
        }

        $table = $this->quoteIdentifier($this->table());
        $primaryKey = $this->quoteColumn($this->model->getPrimaryKey());
        $statement = $this->connection()->prepare("DELETE FROM {$table} WHERE {$primaryKey} = :id");
        $statement->bindValue(':id', $id, is_int($id) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * @return array<int, T>
     */
    public function search(?Criteria $criteria = null): array
    {
        $query = $this->buildSelect($criteria ?? Criteria::create());
        $statement = $this->connection()->prepare($query->sql);
        $this->bind($statement, $query->parameters);
        $statement->execute();

        return array_map(
            fn(array $row): EntityInterface => new $this->modelClass($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * @return array<int, T>
     */
    public function matching(Criteria $criteria): array
    {
        return $this->search($criteria);
    }

    /**
     * @return T|null
     */
    public function first(?Criteria $criteria = null): ?EntityInterface
    {
        $criteria = $criteria !== null ? clone $criteria : Criteria::create();
        $criteria->limit(1);

        return $this->search($criteria)[0] ?? null;
    }

    public function count(?Criteria $criteria = null): int
    {
        $query = $this->buildCount($criteria ?? Criteria::create());
        $statement = $this->connection()->prepare($query->sql);
        $this->bind($statement, $query->parameters);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function buildSelect(Criteria $criteria): StorageQuery
    {
        $table = $this->quoteIdentifier($this->table());
        $where = $this->buildWhere($criteria);
        $sql = "SELECT * FROM {$table}{$where->sql}";

        if ($criteria->sorts() !== []) {
            $parts = [];
            foreach ($criteria->sorts() as $sort) {
                $parts[] = sprintf('%s %s', $this->quoteColumn($sort->field), $sort->normalizedDirection());
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($criteria->limitValue() !== null) {
            $sql .= ' LIMIT ' . $criteria->limitValue();
        }

        if ($criteria->offsetValue() !== null) {
            $sql .= ' OFFSET ' . $criteria->offsetValue();
        }

        return new StorageQuery($sql, $where->parameters);
    }

    private function buildCount(Criteria $criteria): StorageQuery
    {
        $table = $this->quoteIdentifier($this->table());
        $where = $this->buildWhere($criteria);

        return new StorageQuery("SELECT COUNT(*) FROM {$table}{$where->sql}", $where->parameters);
    }

    private function buildWhere(Criteria $criteria): StorageQuery
    {
        $parts = [];
        $parameters = [];
        $counter = 0;

        foreach ($criteria->filters() as $filter) {
            $compiled = $this->compileFilter($filter, $counter);
            if ($compiled->sql === '') {
                continue;
            }

            $parts[] = $compiled->sql;
            $parameters = array_replace($parameters, $compiled->parameters);
        }

        if ($parts === []) {
            return new StorageQuery('', []);
        }

        return new StorageQuery(' WHERE ' . implode(' AND ', $parts), $parameters);
    }

    private function compileFilter(FilterInterface $filter, int &$counter): StorageQuery
    {
        $column = $this->quoteColumn($filter->field());

        if ($filter instanceof EqualsFilter) {
            if ($filter->value === null) {
                return new StorageQuery("{$column} IS NULL", []);
            }

            $parameter = $this->nextParameter($counter);
            return new StorageQuery("{$column} = {$parameter}", [$parameter => $filter->value]);
        }

        if ($filter instanceof NotEqualsFilter) {
            if ($filter->value === null) {
                return new StorageQuery("{$column} IS NOT NULL", []);
            }

            $parameter = $this->nextParameter($counter);
            return new StorageQuery("{$column} != {$parameter}", [$parameter => $filter->value]);
        }

        if ($filter instanceof ContainsFilter) {
            $parameter = $this->nextParameter($counter);
            return new StorageQuery(
                "{$column} LIKE {$parameter} ESCAPE '\\'",
                [$parameter => '%' . $this->escapeLike($filter->value) . '%'],
            );
        }

        if ($filter instanceof InFilter) {
            if ($filter->values === []) {
                return new StorageQuery('1 = 0', []);
            }

            $placeholders = [];
            $parameters = [];
            foreach ($filter->values as $value) {
                $parameter = $this->nextParameter($counter);
                $placeholders[] = $parameter;
                $parameters[$parameter] = $value;
            }

            return new StorageQuery(
                sprintf('%s IN (%s)', $column, implode(', ', $placeholders)),
                $parameters,
            );
        }

        if ($filter instanceof RangeFilter) {
            $parts = [];
            $parameters = [];
            foreach (['gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='] as $property => $operator) {
                $value = $filter->{$property};
                if ($value === null) {
                    continue;
                }

                $parameter = $this->nextParameter($counter);
                $parts[] = "{$column} {$operator} {$parameter}";
                $parameters[$parameter] = $value;
            }

            return new StorageQuery(implode(' AND ', $parts), $parameters);
        }

        throw new InvalidArgumentException(sprintf('Unsupported storage filter "%s".', $filter::class));
    }

    private function nextParameter(int &$counter): string
    {
        return ':p' . $counter++;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function bind(\PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            if ($value === null) {
                $type = PDO::PARAM_NULL;
            }

            $statement->bindValue($name, $value, $type);
        }
    }

    private function table(): string
    {
        if (property_exists($this->model, 'table') && is_string($this->model->table ?? null)) {
            return $this->model->table;
        }

        return $this->model->getTableName();
    }

    /**
     * @return array<int, string>
     */
    private function columns(): array
    {
        $fields = [];
        $reflection = new \ReflectionClass($this->model);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
            $fields[] = $property->getName();
        }

        return array_values(array_unique(array_merge(
            [$this->model->getPrimaryKey()],
            array_diff($fields, [$this->model->getPrimaryKey()]),
        )));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterWritableData(array $data, bool $allowPrimaryKey = false): array
    {
        $allowed = array_fill_keys($this->columns(), true);
        if (!$allowPrimaryKey) {
            unset($allowed[$this->model->getPrimaryKey()]);
        }

        $filtered = [];
        foreach ($data as $field => $value) {
            if (!isset($allowed[(string) $field])) {
                throw new InvalidArgumentException(sprintf('Column not allowed for %s: %s', $this->modelClass, $field));
            }

            $filtered[(string) $field] = $value;
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(EntityInterface $entity, array $data): void
    {
        foreach ($this->filterWritableData($data) as $field => $value) {
            $property = $this->property($field);
            $property->setValue($entity, $value);
        }
    }

    private function property(string $field): \ReflectionProperty
    {
        $reflection = new \ReflectionClass($this->modelClass);
        while (!$reflection->hasProperty($field)) {
            $reflection = $reflection->getParentClass();
            if (!$reflection instanceof \ReflectionClass) {
                throw new InvalidArgumentException(sprintf('Column not allowed for %s: %s', $this->modelClass, $field));
            }
        }

        return $reflection->getProperty($field);
    }

    private function quoteColumn(string $column): string
    {
        if (!in_array($column, $this->columns(), true)) {
            throw new InvalidArgumentException(sprintf('Column not allowed for %s: %s', $this->modelClass, $column));
        }

        return $this->quoteIdentifier($column);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid identifier: %s', $identifier));
        }

        $quote = match (strtolower((string) $this->connection()->getAttribute(PDO::ATTR_DRIVER_NAME))) {
            'mysql' => '`',
            default => '"',
        };

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function connection(): PDO
    {
        $connection = $this->connection ?? database();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('No database connection available for cms()->storage(...).');
        }

        return $connection;
    }

    private function entityManager(): EntityManager
    {
        if ($this->entityManager instanceof EntityManager) {
            return $this->entityManager;
        }

        if ($this->connection instanceof PDO) {
            return new EntityManager($this->connection);
        }

        return em();
    }

    private function withDeprecationsSuppressed(callable $callback): mixed
    {
        $level = error_reporting();
        error_reporting($level & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        try {
            return $callback();
        } finally {
            error_reporting($level);
        }
    }
}
