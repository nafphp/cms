<?php

declare(strict_types=1);

namespace NixPHP\CMS\Storage\Filter;

use NixPHP\CMS\Storage\Filter\FilterInterface;

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
