<?php

declare(strict_types=1);

namespace NixCMS\Stage;

use ReflectionProperty;

final readonly class StageFieldDefinition
{
    public function __construct(
        public string $name,
        public string $property,
        public bool $writable,
        public bool $json,
        public ReflectionProperty $reflection,
    ) {}
}
