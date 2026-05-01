<?php

declare(strict_types=1);

namespace NixCMS\Navigation;

final class NavigationRegistry
{
    /** @var array<string, NavigationItem> */
    private array $items = [];

    public function add(NavigationItem|array $item, bool $replace = false): self
    {
        $item = is_array($item) ? NavigationItem::fromArray($item) : $item;
        if ($item->key() === '') {
            throw new \InvalidArgumentException('Navigation items require a non-empty key.');
        }

        if (isset($this->items[$item->key()]) && !$replace) {
            throw new \LogicException(sprintf('Navigation item "%s" is already registered.', $item->key()));
        }

        $this->items[$item->key()] = $item;
        return $this;
    }

    /** @param iterable<NavigationItem|array> $items */
    public function addMany(iterable $items, bool $replace = false): self
    {
        foreach ($items as $item) {
            $this->add($item, $replace);
        }

        return $this;
    }

    public function get(string $key): ?NavigationItem
    {
        return $this->items[$key] ?? null;
    }

    /** @return array<string, NavigationItem> */
    public function all(): array
    {
        return $this->items;
    }
}
