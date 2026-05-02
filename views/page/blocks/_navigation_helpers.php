<?php

use function NixPHP\View\s;

if (!function_exists('nixcms_page_block_nav_items')) {
    function nixcms_page_block_nav_items(?string $identifier, ?string $language = null): array
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return [];
        }

        return cms()->navigation()->tree($identifier, $language === '' ? null : $language);
    }
}

if (!function_exists('nixcms_page_block_nav')) {
    function nixcms_page_block_nav(array $items, string $presentation = 'horizontal'): string
    {
        $class = $presentation === 'vertical'
            ? 'grid gap-2'
            : 'flex flex-wrap items-center gap-4';

        $html = '<nav class="' . s($class) . '" aria-label="Page navigation">';
        foreach (array_slice($items, 0, 8) as $item) {
            $href = (string) ($item['href'] ?? '#');
            $html .= '<a class="text-sm font-semibold text-[var(--page-muted)] transition hover:text-[var(--page-text)]" href="' . s($href) . '">'
                . s((string) ($item['label'] ?? $item['title'] ?? $item['key'] ?? 'Item'))
                . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }
}
