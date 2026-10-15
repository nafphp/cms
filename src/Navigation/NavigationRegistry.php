<?php

declare(strict_types=1);

namespace Naf\CMS\Navigation;

use InvalidArgumentException;
use LogicException;

final class NavigationRegistry
{
    /** @var array<string, NavigationItem> */
    private array $items = [];
    /** @var array<string, Navigation> */
    private array $navigations = [];

    /**
     * @param NavigationItem|array<string, mixed> $item
     */
    public function add(NavigationItem|array $item, bool $replace = false): self
    {
        $item = is_array($item) ? NavigationItem::fromArray($item) : $item;
        if ($item->key() === '') {
            throw new InvalidArgumentException('Navigation items require a non-empty key.');
        }

        if (isset($this->items[$item->key()]) && !$replace) {
            throw new LogicException(sprintf('Navigation item "%s" is already registered.', $item->key()));
        }

        $this->items[$item->key()] = $item;

        return $this;
    }

    /**
     * @param iterable<NavigationItem|array<string, mixed>> $items
     */
    public function addMany(iterable $items, bool $replace = false): self
    {
        foreach ($items as $item) {
            if ($item instanceof Navigation || (is_array($item) && isset($item['identifier'], $item['items']))) {
                $this->addNavigation($item, $replace);
                continue;
            }

            $this->add($item, $replace);
        }

        return $this;
    }

    /**
     * @param Navigation|array<array-key, mixed> $navigation
     */
    public function addNavigation(Navigation|array $navigation, bool $replace = false): self
    {
        $navigation = is_array($navigation) ? Navigation::fromArray($navigation) : $navigation;
        $key        = $this->navigationKey($navigation->identifier(), $navigation->scope(), $navigation->language());
        if (isset($this->navigations[$key]) && !$replace) {
            throw new LogicException(sprintf('Navigation "%s" is already registered.', $key));
        }

        $this->navigations[$key] = $navigation;

        return $this;
    }

    public function getNavigation(string $identifier, string $scope = 'frontend', ?string $language = null): ?Navigation
    {
        $specificKey = $this->navigationKey($identifier, $scope, $language);
        if (isset($this->navigations[$specificKey])) {
            return $this->navigations[$specificKey];
        }

        if ($language !== null && trim($language) !== '') {
            return $this->navigations[$this->navigationKey($identifier, $scope, null)] ?? null;
        }

        return null;
    }

    /**
     * @return array<string, Navigation>
     */
    public function allNavigationsForScope(string $scope = 'frontend'): array
    {
        $scope = trim($scope) ?: 'frontend';

        return array_filter(
            $this->navigations,
            static fn(Navigation $navigation): bool => $navigation->scope() === $scope,
        );
    }

    public function get(string $key): ?NavigationItem
    {
        return $this->items[$key] ?? null;
    }

    /**
     * @return array<string, NavigationItem>
     */
    public function allForScope(string $scope): array
    {
        $scope = trim($scope);
        if ($scope === '') {
            $scope = 'frontend';
        }

        return array_filter(
            $this->items,
            static fn(NavigationItem $item): bool => $item->scope() === $scope,
        );
    }

    /** @return array<string, NavigationItem> */
    public function all(): array
    {
        return $this->items;
    }

    private function navigationKey(string $identifier, string $scope, ?string $language): string
    {
        $scope    = trim($scope) ?: 'frontend';
        $language = $language === null ? '' : strtolower(trim($language));

        return $scope . ':' . Navigation::normalizeIdentifier($identifier) . ':' . $language;
    }
}
