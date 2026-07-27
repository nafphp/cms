<?php

use NixPHP\CMS\Security\UrlPolicy;
use function NixPHP\View\s;
use function NixPHP\I18n\lang;
use function NixPHP\I18n\t;
use function NixPHP\CMS\cms;

if (!function_exists('nixcms_page_block_nav_items')) {
    function nixcms_page_block_nav_items(?string $identifier, ?string $language = null, ?string $fallbackIdentifier = 'main'): array
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            $identifier = trim((string) $fallbackIdentifier);
        }

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
    function nixcms_page_block_nav(array $items, string $presentation = 'horizontal', string $linkStyle = 'blank', string $linkTheme = 'default'): string
    {
        $linkStyle = in_array($linkStyle, ['blank', 'underline', 'pill', 'rounded-pill', 'filled', 'filled-rounded'], true) ? $linkStyle : 'blank';
        $linkTheme = in_array($linkTheme, ['default', 'accent', 'surface'], true) ? $linkTheme : 'default';
        $class = $presentation === 'vertical'
            ? 'page-navigation page-navigation--vertical grid gap-2'
            : 'page-navigation page-navigation--horizontal flex flex-wrap items-center gap-4';
        $class .= ' page-navigation--style-' . $linkStyle . ' page-navigation--theme-' . $linkTheme;

        $html = '<nav class="' . s($class) . '" aria-label="Page navigation">';
        foreach (array_slice($items, 0, 8) as $item) {
            $href = UrlPolicy::link((string) ($item['href'] ?? '#')) ?? '#';
            $html .= '<a class="page-navigation__link text-sm font-semibold transition" href="' . s($href) . '">'
                . s(t((string) ($item['label'] ?? $item['title'] ?? $item['key'] ?? 'Item')))
                . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }
}
