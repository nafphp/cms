<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article\Block;

use NixPHP\CMS\Content\Article\Block\AbstractBlock;

final class ListBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        $tag = ((string) ($data['style'] ?? 'unordered')) === 'ordered' ? 'ol' : 'ul';
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        $html = array_map(fn(mixed $item): string => '<li>' . $this->cleanInline((string) $item) . '</li>', $items);

        return '<' . $tag . '>' . implode('', $html) . '</' . $tag . '>';
    }
}
