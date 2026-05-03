<?php

declare(strict_types=1);

namespace NixCMS\Storage;

final readonly class Sort
{
    public function __construct(
        public string $field,
        public string $direction = 'ASC',
    ) {
    }

    public function normalizedDirection(): string
    {
        return strtoupper($this->direction) === 'DESC' ? 'DESC' : 'ASC';
    }
}
