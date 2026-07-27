<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

interface DocBlockRenderer
{
    public function name(): string;

    public function render(): string;
}
