<?php

declare(strict_types=1);

namespace NixPHP\CMS\Storage\Filter;

use NixPHP\CMS\Storage\Filter\FilterInterface;

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
