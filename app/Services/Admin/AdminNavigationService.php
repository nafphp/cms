<?php

declare(strict_types=1);

namespace NixCMS\Services\Admin;

use NixCMS\Navigation\NavigationRegistry;
use NixCMS\Page\Page;
use NixCMS\Page\PageRegistry;
use NixCMS\Support\UserHelper;
use function NixPHP\config;
use function NixCMS\cms;
use function NixCMS\user;

final class AdminNavigationService
{
    private const SCOPE = 'admin';

    public function __construct(
        private ?NavigationRegistry $navigation = null,
        private ?PageRegistry $pages = null,
        private bool $includeConfiguredItems = true,
        private mixed $userResolver = null,
        private ?array $accentColors = null,
    ) {
    }

    public function items(): array
    {
        return $this->filterTreeByAuthorization(
            $this->applyInheritedAccents(
                $this->buildTree($this->flatItems()),
                null,
                $this->resolvedAccentColors(),
            )
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function flatItems(): array
    {
        $items = [];
        foreach ($this->navigation()->allForScope(self::SCOPE) as $item) {
            $data = $item->toArray();
            $items[$data['key']] = $this->normalizeItem($data);
        }

        if ($this->includeConfiguredItems) {
            $this->mergeConfigItems($items);
        }
        $this->mergePageItems($items);

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $items
     */
    private function mergeConfigItems(array &$items): void
    {
        $pluginItems = config('cms:admin:navigation', []);
        if (!is_array($pluginItems)) {
            return;
        }

        foreach ($pluginItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = (string) ($item['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $items[$key] = array_replace($items[$key] ?? [], $this->normalizeItem($item));
        }
    }

    /**
     * @param array<string, array<string, mixed>> $items
     */
    private function mergePageItems(array &$items): void
    {
        $routeNameAttachments = [];
        foreach ($this->pages()->all() as $page) {
            $navigation = $page->meta()->navigation;
            if ($navigation->scope !== self::SCOPE) {
                continue;
            }

            $parent = $navigation->parentKey();
            $key = $navigation->key ?? $page->id();

            if ($navigation->visible && $navigation->label !== null) {
                $existingRouteNames = is_array($items[$key]['routeNames'] ?? null) ? $items[$key]['routeNames'] : [];
                $item = array_replace($items[$key] ?? [], $this->normalizeItem([
                    'key' => $key,
                    'label' => $navigation->label,
                    'href' => $navigation->href ?? $this->hrefForPage($page),
                    'parent' => $parent,
                    'icon' => $navigation->icon,
                    'order' => $navigation->order,
                    'visible' => true,
                    'scope' => $navigation->scope,
                    'routeNames' => [$page->id()],
                ]));
                $item['routeNames'] = $this->routeNames([...$existingRouteNames, ...$item['routeNames']]);
                $items[$key] = $item;
                continue;
            }

            if ($parent !== null) {
                $routeNameAttachments[$parent][] = $page->id();
            }
        }

        foreach ($routeNameAttachments as $parent => $routeNames) {
            if (!isset($items[$parent])) {
                $items[$parent] = $this->normalizeItem([
                    'key' => $parent,
                    'label' => $this->labelFromKey((string) $parent),
                    'href' => '#',
                    'parent' => $this->parentFromKey((string) $parent),
                    'visible' => true,
                ]);
            }

            $items[$parent]['routeNames'] = $this->routeNames([
                ...$items[$parent]['routeNames'],
                ...$routeNames,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *     key:string,
     *     label:string,
     *     href:string,
     *     parent:string|null,
     *     icon:string,
     *     order:int,
     *     visible:bool,
     *     scope:string,
     *     meta:array<string, mixed>,
     *     accent:string|null,
     *     routeNames:array<int, string>,
     *     children:array<int, array<string, mixed>>
     * }
     */
    private function normalizeItem(array $item): array
    {
        $key = (string) ($item['key'] ?? '');

        return [
            'key' => $key,
            'label' => (string) ($item['label'] ?? $this->labelFromKey($key)),
            'href' => (string) ($item['href'] ?? '#'),
            'parent' => isset($item['parent']) && trim((string) $item['parent']) !== ''
                ? trim((string) $item['parent'])
                : null,
            'icon' => (string) ($item['icon'] ?? 'dot'),
            'order' => (int) ($item['order'] ?? 100),
            'visible' => (bool) ($item['visible'] ?? true),
            'scope' => (string) ($item['scope'] ?? self::SCOPE),
            'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : [],
            'accent' => isset($item['accent']) && trim((string) $item['accent']) !== ''
                ? trim((string) $item['accent'])
                : null,
            'routeNames' => $this->routeNames(is_array($item['routeNames'] ?? null) ? $item['routeNames'] : []),
            'children' => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $items): array
    {
        $knownKeys = array_keys($items);
        foreach ($knownKeys as $key) {
            $parent = $items[$key]['parent'] ?? null;
            $scope = (string) ($items[$key]['scope'] ?? self::SCOPE);
            if (!is_string($parent) || $parent === '') {
                continue;
            }

            while ($parent !== '') {
                if (!isset($items[$parent])) {
                    $items[$parent] = $this->normalizeItem([
                        'key' => $parent,
                        'label' => $this->labelFromKey($parent),
                        'href' => '#',
                        'parent' => $this->parentFromKey($parent),
                        'visible' => true,
                        'scope' => $scope,
                    ]);
                }

                $parent = (string) ($items[$parent]['parent'] ?? '');
            }
        }

        $childrenByParent = [];
        foreach ($items as $key => $item) {
            if (($item['visible'] ?? true) !== true) {
                continue;
            }

            $parent = $item['parent'] ?? null;
            if (is_string($parent) && $parent !== '') {
                $childrenByParent[$parent][$key] = $item;
                continue;
            }

            $childrenByParent[''][$key] = $item;
        }

        $build = function (string $parent) use (&$build, &$childrenByParent): array {
            $children = array_values($childrenByParent[$parent] ?? []);
            usort($children, $this->sortItems(...));

            foreach ($children as &$child) {
                $child['children'] = $build((string) $child['key']);
                $child['routeNames'] = $this->routeNames([
                    ...$child['routeNames'],
                    ...$this->descendantRouteNames($child['children']),
                ]);
                if (($child['scope'] ?? self::SCOPE) !== self::SCOPE) {
                    $child['routeNames'] = $this->descendantRouteNames($child['children']);
                }
            }
            unset($child);

            return array_values(array_filter(
                $children,
                static fn(array $child): bool => ($child['scope'] ?? self::SCOPE) === self::SCOPE
                    || (is_array($child['children'] ?? null) && $child['children'] !== [])
            ));
        };

        return $build('');
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<int, string>
     */
    private function descendantRouteNames(array $children): array
    {
        $routeNames = [];
        foreach ($children as $child) {
            $routeNames = [
                ...$routeNames,
                ...(is_array($child['routeNames'] ?? null) ? $child['routeNames'] : []),
                ...$this->descendantRouteNames(is_array($child['children'] ?? null) ? $child['children'] : []),
            ];
        }

        return $this->routeNames($routeNames);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function filterTreeByAuthorization(array $items): array
    {
        $visible = [];

        foreach ($items as $item) {
            $children = $this->filterTreeByAuthorization(
                is_array($item['children'] ?? null) ? $item['children'] : []
            );
            $item['children'] = $children;

            if ($children !== [] || $this->isItemAuthorized($item)) {
                $item['routeNames'] = $this->routeNames([
                    ...$this->authorizedRouteNames(is_array($item['routeNames'] ?? null) ? $item['routeNames'] : []),
                    ...$this->descendantRouteNames($children),
                ]);
                $visible[] = $item;
            }
        }

        return $visible;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isItemAuthorized(array $item): bool
    {
        $routeNames = $this->routeNames(is_array($item['routeNames'] ?? null) ? $item['routeNames'] : []);
        if ($routeNames === []) {
            return true;
        }

        return $this->authorizedRouteNames($routeNames) !== [];
    }

    /**
     * @param array<int, mixed> $routeNames
     * @return array<int, string>
     */
    private function authorizedRouteNames(array $routeNames): array
    {
        $authorized = [];
        $routeNames = $this->routeNames($routeNames);
        foreach ($routeNames as $routeName) {
            $page = $this->pages()->get($routeName);
            if (!$page instanceof Page || $this->isPageAuthorized($page)) {
                $authorized[] = $routeName;
            }
        }

        return $this->routeNames($authorized);
    }

    private function isPageAuthorized(Page $page): bool
    {
        $user = $this->currentUser();

        if ($page->requiresAuthentication() && ($user === null || !$user->isLoggedIn())) {
            return false;
        }

        foreach ($page->requiredRoles() as $role) {
            if ($user?->hasRole($role) !== true) {
                return false;
            }
        }

        $permissions = $page->requiredPermissions();
        if ($permissions === []) {
            return true;
        }

        if ($page->requiresAnyPermission()) {
            foreach ($permissions as $permission) {
                if ($user?->can($permission) === true) {
                    return true;
                }
            }

            return false;
        }

        foreach ($permissions as $permission) {
            if ($user?->can($permission) !== true) {
                return false;
            }
        }

        return true;
    }

    private function currentUser(): ?UserHelper
    {
        if (is_callable($this->userResolver)) {
            $user = ($this->userResolver)();
            return $user instanceof UserHelper ? $user : null;
        }

        return user();
    }

    /**
     * @param array<int, mixed> $routeNames
     * @return array<int, string>
     */
    private function routeNames(array $routeNames): array
    {
        $normalized = [];
        foreach ($routeNames as $routeName) {
            $routeName = trim((string) $routeName);
            if ($routeName !== '' && !in_array($routeName, $normalized, true)) {
                $normalized[] = $routeName;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function sortItems(array $left, array $right): int
    {
        $order = ((int) ($left['order'] ?? 100)) <=> ((int) ($right['order'] ?? 100));
        if ($order !== 0) {
            return $order;
        }

        return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    }

    private function hrefForPage(Page $page): string
    {
        $path = $page->path();
        return str_contains($path, '{') ? '#' : $path;
    }

    private function labelFromKey(string $key): string
    {
        $parts = explode('.', str_replace(['-', '_'], ' ', $key));
        $label = trim((string) end($parts));

        return ucwords($label);
    }

    private function parentFromKey(string $key): ?string
    {
        if (!str_contains($key, '.')) {
            return null;
        }

        return substr($key, 0, (int) strrpos($key, '.')) ?: null;
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
                'parent' => $this->parentFromKey($key),
            ];
            array_unshift($chain, $item);

            $parent = isset($item['parent']) ? trim((string) $item['parent']) : '';
            if ($parent === '') {
                $parent = (string) ($this->parentFromKey($key) ?? '');
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
            } catch (\LogicException) {
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
            } catch (\Throwable) {
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

    private function navigation(): NavigationRegistry
    {
        return $this->navigation ??= cms()->navigationRegistry();
    }

    private function pages(): PageRegistry
    {
        return $this->pages ??= cms()->pages();
    }

    public function accentForPath(string $path): string
    {
        $colors = $this->resolvedAccentColors();
        $items = $this->flatItems();

        $key = $this->navigationKeyForPath($path) ?? $this->pathSegmentNavigationKey($path);
        if ($key !== null) {
            $accent = $this->accentForNavigationKey($key, $items, $colors);
            if ($accent !== null) {
                return $accent;
            }
        }

        return $this->fallbackAccent($colors);
    }
}
