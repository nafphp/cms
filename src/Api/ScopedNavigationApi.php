<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use Naf\CMS\Enums\Scope;
use Naf\CMS\Navigation\Navigation;
use Naf\CMS\Navigation\NavigationItem;

final class ScopedNavigationApi
{
    public function __construct(
        private readonly NavigationApi $navigation,
        private readonly Scope $scope,
    ) {
    }

    /**
     * @param NavigationItem|array<string, mixed> $item
     */
    public function add(NavigationItem|array $item, bool $replace = false): self
    {
        $this->navigation->add($this->scopedItem($item), $replace);

        return $this;
    }

    /**
     * @param iterable<NavigationItem|Navigation|array<string, mixed>> $items
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
        $this->navigation->addNavigation($this->scopedNavigation($navigation), $replace);

        return $this;
    }

    public function get(string $identifier, ?string $language = null): ?Navigation
    {
        return $this->navigation->get($identifier, $language, $this->scope->value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(string $identifier, ?string $language = null): array
    {
        return $this->navigation->tree($identifier, $language, $this->scope->value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaries(): array
    {
        return $this->navigation->summaries($this->scope->value);
    }

    /**
     * @return array<string, Navigation>
     */
    public function translations(string $identifier): array
    {
        return $this->navigation->translations($identifier, $this->scope->value);
    }

    public function item(string $key): ?NavigationItem
    {
        $item = $this->navigation->item($key);

        return $item instanceof NavigationItem && $item->scope() === $this->scope->value ? $item : null;
    }

    /**
     * @return array<string, NavigationItem>
     */
    public function all(): array
    {
        return $this->navigation->allForScope($this->scope->value);
    }

    /**
     * @param NavigationItem|array<string, mixed> $item
     * @return NavigationItem|array<string, mixed>
     */
    private function scopedItem(NavigationItem|array $item): NavigationItem|array
    {
        if ($item instanceof NavigationItem) {
            return $item->setScope($this->scope->value);
        }

        $item['scope'] = $this->scope->value;

        return $item;
    }

    /**
     * @param Navigation|array<string, mixed> $navigation
     * @return Navigation|array<string, mixed>
     */
    private function scopedNavigation(Navigation|array $navigation): Navigation|array
    {
        if ($navigation instanceof Navigation) {
            return $navigation->setScope($this->scope->value);
        }

        $navigation['scope'] = $this->scope->value;

        return $navigation;
    }
}
