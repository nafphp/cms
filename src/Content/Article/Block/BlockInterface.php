<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article\Block;

interface BlockInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string;
}
