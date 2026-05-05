<?php

use function NixPHP\View\s;
use function NixPHP\I18n\lang;
use function NixPHP\I18n\t;

if (!function_exists('nixcms_page_block_nav_items')) {
    function nixcms_page_block_nav_items(?string $identifier, ?string $language = null, ?string $fallbackIdentifier = 'main'): array
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return [];
        }

        $language = $language === null ? lang() : $language;
        $language = $language === '' ? null : $language;

        $items = cms()->navigation()->tree($identifier, $language);
        $fallbackIdentifier = trim((string) $fallbackIdentifier);
        if ($items === [] && $fallbackIdentifier !== '' && $fallbackIdentifier !== $identifier) {
            return cms()->navigation()->tree($fallbackIdentifier, $language);
        }

        return $items;
    }
}

if (!function_exists('nixcms_page_block_nav')) {
    function nixcms_page_block_nav(array $items, string $presentation = 'horizontal'): string
    {
        $class = $presentation === 'vertical'
            ? 'page-navigation page-navigation--vertical grid gap-2'
            : 'page-navigation page-navigation--horizontal flex flex-wrap items-center gap-4';

        $html = '<nav class="' . s($class) . '" aria-label="Page navigation">';
        foreach (array_slice($items, 0, 8) as $item) {
            $href = (string) ($item['href'] ?? '#');
            $html .= '<a class="page-navigation__link text-sm font-semibold text-[var(--page-muted)] transition hover:text-[var(--page-text)]" href="' . s($href) . '">'
                . s(t((string) ($item['label'] ?? $item['title'] ?? $item['key'] ?? 'Item')))
                . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }
}
