<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Article\Block;

use Naf\CMS\Security\InlineHtmlSanitizer;

use function Naf\View\s;

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
