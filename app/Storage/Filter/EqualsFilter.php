<?php

declare(strict_types=1);

namespace NixCMS\Storage\Filter;

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
