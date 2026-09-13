<?php

declare(strict_types=1);

namespace Naf\CMS\Support\Docs;

interface DocBlockRenderer
{
    public function name(): string;

    public function render(): string;
}
