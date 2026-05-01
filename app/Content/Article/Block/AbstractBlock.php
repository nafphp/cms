<?php

declare(strict_types=1);

namespace NixCMS\Content\Article\Block;

abstract class AbstractBlock implements BlockInterface
{
    protected const INLINE_TAGS = '<b><strong><i><em><u><a><mark><code><br>';

    protected function cleanInline(string $html): string
    {
        return strip_tags($html, self::INLINE_TAGS);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
