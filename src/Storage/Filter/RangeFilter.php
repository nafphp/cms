<?php

declare(strict_types=1);

namespace NixPHP\CMS\Storage\Filter;

use NixPHP\CMS\Storage\Filter\FilterInterface;

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
