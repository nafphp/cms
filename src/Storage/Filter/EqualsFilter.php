<?php

declare(strict_types=1);

namespace src\Storage\Filter;

use src\Storage\Filter\FilterInterface;

final readonly class EqualsFilter implements FilterInterface
{
    public function __construct(
        private string $field,
        public mixed $value,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }
}
