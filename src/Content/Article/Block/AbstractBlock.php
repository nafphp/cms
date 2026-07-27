<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article\Block;

use NixPHP\CMS\Security\InlineHtmlSanitizer;
use NixPHP\CMS\Content\Article\Block\BlockInterface;
use function NixPHP\View\s;

abstract class AbstractBlock implements BlockInterface
{
    protected function cleanInline(string $html): string
    {
        return (new InlineHtmlSanitizer())->sanitize($html);
    }

    protected function escape(string $value): string
    {
        return (string) s($value);
    }
}
