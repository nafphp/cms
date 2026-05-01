<?php

declare(strict_types=1);

namespace NixCMS\Services\Admin;

use NixCMS\Navigation\NavigationRegistry;
use NixCMS\Page\Page;
use NixCMS\Page\PageRegistry;
use function NixPHP\config;

final class AdminNavigationService
{
    private const SCOPE = 'admin';

    public function __construct(
        private ?NavigationRegistry $navigation = null,
        private ?PageRegistry $pages = null,
        private bool $includeConfiguredItems = true,
    ) {
    }

    public function items(): array
    {
        return $this->buildTree($this->flatItems());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function flatItems(): array
    {
        $items = [];
        foreach ($this->navigation()->all() as $item) {
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

    private function navigation(): NavigationRegistry
    {
        return $this->navigation ??= \cms()->navigation();
    }

    private function pages(): PageRegistry
    {
        return $this->pages ??= \cms()->pages();
    }

    public function accentForPath(string $path): string
    {
        $colors = config('cms:admin:accent_colors', []);
        if (!is_array($colors)) {
            $colors = [];
        }

        $key = match (true) {
            $path === '/admin' => 'dashboard',
            str_starts_with($path, '/admin/articles') => 'content',
            str_starts_with($path, '/admin/users') => 'users',
            str_starts_with($path, '/admin/roles') => 'roles',
            str_starts_with($path, '/admin/translations') => 'settings',
            str_starts_with($path, '/admin/settings/audit') => 'settings',
            str_starts_with($path, '/admin/settings') => 'settings',
            default => 'settings',
        };

        return (string) ($colors[$key] ?? $colors['articles'] ?? '#64748b');
    }
}
