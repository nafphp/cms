<?php

declare(strict_types=1);

namespace src\Storage;

use BackedEnum;
use DateTimeInterface;
use src\Storage\Filter\ContainsFilter;
use src\Storage\Filter\EqualsFilter;
use src\Storage\Filter\FilterInterface;
use src\Storage\Filter\InFilter;
use src\Storage\Filter\NotEqualsFilter;
use src\Storage\Filter\RangeFilter;
use src\Storage\Sort;

final class Criteria
{
    /**
     * @var array<int, FilterInterface>
     */
    private array $filters = [];

    /**
     * @var array<int, Sort>
     */
    private array $sorts = [];

    private ?int $limit = null;
    private ?int $offset = null;

    public static function create(): self
    {
        return new self();
    }

    public function equals(string $field, mixed $value): self
    {
        return $this->addFilter(new EqualsFilter($field, $this->normalizeValue($value)));
    }

    public function where(string $field, mixed $value): self
    {
        return $this->equals($field, $value);
    }

    public function notEquals(string $field, mixed $value): self
    {
        return $this->addFilter(new NotEqualsFilter($field, $this->normalizeValue($value)));
    }

    /**
     * @param array<int, mixed> $values
     */
    public function in(string $field, array $values): self
    {
        return $this->addFilter(new InFilter($field, array_map($this->normalizeValue(...), $values)));
    }

    public function contains(string $field, string $value): self
    {
        return $this->addFilter(new ContainsFilter($field, $value));
    }

    public function gt(string $field, mixed $value): self
    {
        return $this->range($field, gt: $value);
    }

    public function gte(string $field, mixed $value): self
    {
        return $this->range($field, gte: $value);
    }

    public function lt(string $field, mixed $value): self
    {
        return $this->range($field, lt: $value);
    }

    public function lte(string $field, mixed $value): self
    {
        return $this->range($field, lte: $value);
    }

    public function range(
        string $field,
        mixed $gt = null,
        mixed $gte = null,
        mixed $lt = null,
        mixed $lte = null,
    ): self {
        return $this->addFilter(new RangeFilter(
            $field,
            $this->normalizeValue($gt),
            $this->normalizeValue($gte),
            $this->normalizeValue($lt),
            $this->normalizeValue($lte),
        ));
    }

    public function addFilter(FilterInterface $filter): self
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function sort(string $field, string $direction = 'ASC'): self
    {
        $this->sorts[] = new Sort($field, $direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function page(int $page, int $perPage = 20): self
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        return $this
            ->limit($perPage)
            ->offset(($page - 1) * $perPage);
    }

    /**
     * @return array<int, FilterInterface>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<int, Sort>
     */
    public function sorts(): array
    {
        return $this->sorts;
    }

    public function limitValue(): ?int
    {
        return $this->limit;
    }

    public function offsetValue(): ?int
    {
        return $this->offset;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }
}
