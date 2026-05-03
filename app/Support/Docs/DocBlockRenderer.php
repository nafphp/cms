<?php

declare(strict_types=1);

namespace NixCMS\Support\Docs;

interface DocBlockRenderer
{
    public function name(): string;

    public function render(): string;
}
