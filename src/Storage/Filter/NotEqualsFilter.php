<?php

declare(strict_types=1);

namespace NixPHP\CMS\Storage\Filter;

use NixPHP\CMS\Storage\Filter\FilterInterface;

final readonly class NotEqualsFilter implements FilterInterface
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
