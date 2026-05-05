<?php

declare(strict_types=1);

namespace NixCMS\Stage\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class StageField
{
    public function __construct(
        public ?string $name = null,
        public bool $writable = true,
        public bool $json = false,
    ) {}
}
