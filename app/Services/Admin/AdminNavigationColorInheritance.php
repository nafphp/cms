<?php

declare(strict_types=1);

namespace NixCMS\Services\Admin;

use NixCMS\Page\PageRegistry;
use function NixCMS\cms;
use function NixPHP\config;
use function NixPHP\log;

final class AdminNavigationColorInheritance
{
    private const SCOPE = 'admin';

    public function __construct(
        private ?PageRegistry $pages = null,
        private ?array $accentColors = null,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $items): array
    {
        return $this->applyInheritedAccents($items, null, $this->resolvedAccentColors());
    }

    /**
     * @param array<string, array<string, mixed>> $items
     */
    public function accentForPath(string $path, array $items): string
    {
        $colors = $this->resolvedAccentColors();

        $key = $this->navigationKeyForPath($path) ?? $this->pathSegmentNavigationKey($path);
        if ($key !== null) {
            $accent = $this->accentForNavigationKey($key, $items, $colors);
            if ($accent !== null) {
                return $accent;
            }
        }

        return $this->fallbackAccent($colors);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, string> $colors
     * @return array<int, array<string, mixed>>
     */
    private function applyInheritedAccents(array $items, ?string $inheritedAccent, array $colors): array
    {
        foreach ($items as &$item) {
            $directAccent = $this->directAccentForItem($item, $colors);
            $accent = $inheritedAccent ?? $directAccent ?? $this->fallbackAccent($colors);
            $item['accent'] = $accent;
            $item['children'] = $this->applyInheritedAccents(
                is_array($item['children'] ?? null) ? $item['children'] : [],
                $inheritedAccent ?? $directAccent,
                $colors,
            );
        }
        unset($item);

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $colors
     */
    private function directAccentForItem(array $item, array $colors): ?string
    {
        $accent = isset($item['accent']) ? trim((string) $item['accent']) : '';
        if ($accent !== '') {
            return $accent;
        }

        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        foreach (['accent', 'adminAccent', 'admin_accent'] as $metaKey) {
            $accent = isset($meta[$metaKey]) ? trim((string) $meta[$metaKey]) : '';
            if ($accent !== '') {
                return $accent;
            }
        }

        $key = (string) ($item['key'] ?? '');
        $accent = isset($colors[$key]) ? trim((string) $colors[$key]) : '';

        return $accent !== '' ? $accent : null;
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @param array<string, string> $colors
     */
    private function accentForNavigationKey(string $key, array $items, array $colors): ?string
    {
        $visited = [];
        $chain = [];
        $key = trim($key);

        while ($key !== '' && !isset($visited[$key])) {
            $visited[$key] = true;
            $item = $items[$key] ?? [
                'key' => $key,
                'parent' => AdminNavigationItemCollector::parentFromKey($key),
            ];
            array_unshift($chain, $item);

            $parent = isset($item['parent']) ? trim((string) $item['parent']) : '';
            if ($parent === '') {
                $parent = (string) (AdminNavigationItemCollector::parentFromKey($key) ?? '');
            }

            if ($parent === '' || $parent === $key) {
                break;
            }

            $key = $parent;
        }

        foreach ($chain as $item) {
            $accent = $this->directAccentForItem($item, $colors);
            if ($accent !== null) {
                return $accent;
            }
        }

        return null;
    }

    private function navigationKeyForPath(string $path): ?string
    {
        $path = $this->normalizePath($path);
        $bestKey = null;
        $bestScore = -1;

        foreach ($this->pages()->all() as $page) {
            try {
                $pagePath = $page->path();
            } catch (\LogicException $e) {
                log()->debug('AdminNavigationColorInheritance.pagePath unavailable', [
                    'exception' => $e,
                    'page_id' => $page->id(),
                ]);
                continue;
            }

            if (!$this->pagePathMatches($pagePath, $path)) {
                continue;
            }

            $navigation = $page->meta()->navigation;
            if ($navigation->scope !== self::SCOPE) {
                continue;
            }

            $key = $navigation->visible && $navigation->label !== null
                ? ($navigation->key ?? $page->id())
                : $navigation->parentKey();

            if ($key === null || trim($key) === '') {
                continue;
            }

            $score = strlen($pagePath) + ($this->normalizePath($pagePath) === $path ? 1000 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = trim($key);
            }
        }

        return $bestKey;
    }

    private function pathSegmentNavigationKey(string $path): ?string
    {
        $path = $this->normalizePath($path);
        if ($path === '/admin') {
            return 'dashboard';
        }

        if (!str_starts_with($path, '/admin/')) {
            return null;
        }

        $parts = explode('/', trim($path, '/'));
        $key = trim((string) ($parts[1] ?? ''));

        return $key !== '' ? $key : null;
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH);
        $path = is_string($path) ? '/' . ltrim(trim($path), '/') : '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function pagePathMatches(string $pagePath, string $path): bool
    {
        $pagePath = $this->normalizePath($pagePath);
        if ($pagePath === $path) {
            return true;
        }

        $quoted = preg_quote($pagePath, '#');
        $pattern = preg_replace('#\\\\\{[^}]+\\\\\}#', '[^/]+', $quoted);
        if (!is_string($pattern)) {
            return false;
        }

        return preg_match('#^' . $pattern . '$#', $path) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function resolvedAccentColors(): array
    {
        $colors = $this->accentColors;
        if ($colors === null) {
            try {
                $configured = config('cms:admin:accent_colors', []);
                $colors = is_array($configured) ? $configured : [];
            } catch (\Throwable $e) {
                log()->warning('AdminNavigationColorInheritance.accentColors failed', [
                    'exception' => $e,
                ]);
                $colors = [];
            }
        }

        $normalized = [];
        foreach ($colors as $key => $color) {
            $key = trim((string) $key);
            $color = trim((string) $color);
            if ($key !== '' && $color !== '') {
                $normalized[$key] = $color;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $colors
     */
    private function fallbackAccent(array $colors): string
    {
        foreach (['content', 'articles', 'dashboard', 'settings'] as $key) {
            $color = trim((string) ($colors[$key] ?? ''));
            if ($color !== '') {
                return $color;
            }
        }

        return '#64748b';
    }

    private function pages(): PageRegistry
    {
        return $this->pages ??= cms()->pages();
    }
}
