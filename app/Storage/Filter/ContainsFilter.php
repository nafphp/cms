<?php

declare(strict_types=1);

namespace NixCMS\Storage\Filter;

final readonly class ContainsFilter implements FilterInterface
{
    public function __construct(
        private string $field,
        public string $value,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }
}
