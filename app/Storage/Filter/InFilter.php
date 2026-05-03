<?php

declare(strict_types=1);

namespace NixCMS\Storage\Filter;

final readonly class InFilter implements FilterInterface
{
    /**
     * @param array<int, mixed> $values
     */
    public function __construct(
        private string $field,
        public array $values,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }
}
