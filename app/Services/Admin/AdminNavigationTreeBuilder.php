<?php

declare(strict_types=1);

namespace NixCMS\Services\Admin;

final class AdminNavigationTreeBuilder
{
    private const SCOPE = 'admin';

    /**
     * @param array<string, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function build(array $items): array
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
                    $items[$parent] = AdminNavigationItemCollector::normalizeItem([
                        'key' => $parent,
                        'label' => AdminNavigationItemCollector::labelFromKey($parent),
                        'href' => '#',
                        'parent' => AdminNavigationItemCollector::parentFromKey($parent),
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
                $child['routeNames'] = AdminNavigationItemCollector::routeNames([
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
    public function descendantRouteNames(array $children): array
    {
        $routeNames = [];
        foreach ($children as $child) {
            $routeNames = [
                ...$routeNames,
                ...(is_array($child['routeNames'] ?? null) ? $child['routeNames'] : []),
                ...$this->descendantRouteNames(is_array($child['children'] ?? null) ? $child['children'] : []),
            ];
        }

        return AdminNavigationItemCollector::routeNames($routeNames);
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
}
