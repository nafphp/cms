<?php

declare(strict_types=1);

namespace Naf\CMS\Storage\Filter;

use Naf\CMS\Storage\Filter\FilterInterface;

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
