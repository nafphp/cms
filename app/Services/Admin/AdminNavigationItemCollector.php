<?php

declare(strict_types=1);

namespace NixCMS\Services\Admin;

use NixCMS\Api\ScopedNavigationApi;
use NixCMS\Enums\Scope;
use NixCMS\Page\Page;
use NixCMS\Page\PageRegistry;
use function NixCMS\cms;
use function NixPHP\config;

final class AdminNavigationItemCollector
{
    private const SCOPE = 'admin';

    public function __construct(
        private ?ScopedNavigationApi $navigation = null,
        private ?PageRegistry $pages = null,
        private bool $includeConfiguredItems = true,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function collect(): array
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
                $item['routeNames'] = self::routeNames([...$existingRouteNames, ...$item['routeNames']]);
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
                    'label' => self::labelFromKey((string) $parent),
                    'href' => '#',
                    'parent' => self::parentFromKey((string) $parent),
                    'visible' => true,
                ]);
            }

            $items[$parent]['routeNames'] = self::routeNames([
                ...$items[$parent]['routeNames'],
                ...$routeNames,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function normalizeItem(array $item): array
    {
        $key = (string) ($item['key'] ?? '');

        return [
            'key' => $key,
            'label' => (string) ($item['label'] ?? self::labelFromKey($key)),
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
            'routeNames' => self::routeNames(is_array($item['routeNames'] ?? null) ? $item['routeNames'] : []),
            'children' => [],
        ];
    }

    /**
     * @param array<int, mixed> $routeNames
     * @return array<int, string>
     */
    public static function routeNames(array $routeNames): array
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

    public static function labelFromKey(string $key): string
    {
        $parts = explode('.', str_replace(['-', '_'], ' ', $key));
        $label = trim((string) end($parts));

        return ucwords($label);
    }

    public static function parentFromKey(string $key): ?string
    {
        if (!str_contains($key, '.')) {
            return null;
        }

        return substr($key, 0, (int) strrpos($key, '.')) ?: null;
    }

    private function hrefForPage(Page $page): string
    {
        $path = $page->path();
        return str_contains($path, '{') ? '#' : $path;
    }

    private function navigation(): ScopedNavigationApi
    {
        return $this->navigation ??= cms()->scope(Scope::ADMIN)->navigation();
    }

    private function pages(): PageRegistry
    {
        return $this->pages ??= cms()->pages();
    }
}
