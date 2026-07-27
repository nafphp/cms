<?php

declare(strict_types=1);

namespace src\Storage\Filter;

use src\Storage\Filter\FilterInterface;

final readonly class RangeFilter implements FilterInterface
{
    public function __construct(
        private string $field,
        public mixed $gt = null,
        public mixed $gte = null,
        public mixed $lt = null,
        public mixed $lte = null,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }
}
