<?php

declare(strict_types=1);

namespace Naf\CMS\Navigation;

final class NavigationItem
{
    /** @var array<int, string> */
    private array $routeNames = [];
    private ?string $label = null;
    private ?string $href = null;
    private ?string $parent = null;
    private ?string $icon = null;
    private string $type = 'url';
    private ?string $target = null;
    /** @var array<string, mixed> */
    private array $meta = [];
    /** @var array<int, NavigationItem> */
    private array $children = [];
    private int $order = 100;
    private bool $visible = true;
    private string $scope = 'frontend';

    private function __construct(private readonly string $key)
    {
    }

    public static function create(string $key): self
    {
        return new self($key);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $item = self::create((string) ($data['key'] ?? ''));
        $item->label = isset($data['label']) ? (string) $data['label'] : null;
        $item->href = isset($data['href']) ? (string) $data['href'] : null;
        $item->parent = isset($data['parent']) ? (string) $data['parent'] : null;
        $item->icon = isset($data['icon']) ? (string) $data['icon'] : null;
        $item->type = trim((string) ($data['type'] ?? 'url')) ?: 'url';
        $item->target = isset($data['target']) ? (string) $data['target'] : null;
        $item->meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $item->order = (int) ($data['order'] ?? 100);
        $item->visible = (bool) ($data['visible'] ?? true);
        $item->scope = (string) ($data['scope'] ?? 'frontend');

        if (is_array($data['routeNames'] ?? null)) {
            $item->setRouteNames(array_map('strval', $data['routeNames']));
        }
        if (is_array($data['children'] ?? null)) {
            foreach ($data['children'] as $child) {
                if (is_array($child)) {
                    $item->addChild(self::fromArray($child));
                }
            }
        }

        return $item;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);
        return $this;
    }

    public function setHref(?string $href): self
    {
        $this->href = $href === null ? null : trim($href);
        return $this;
    }

    public function setParent(?string $parent): self
    {
        $parent = $parent === null ? null : trim($parent);
        $this->parent = $parent === '' ? null : $parent;
        return $this;
    }

    public function setIcon(?string $icon): self
    {
        $icon = $icon === null ? null : trim($icon);
        $this->icon = $icon === '' ? null : $icon;
        return $this;
    }

    public function setType(string $type): self
    {
        $type = trim($type);
        $this->type = $type === '' ? 'url' : $type;
        return $this;
    }

    public function setTarget(?string $target): self
    {
        $target = $target === null ? null : trim($target);
        $this->target = $target === '' ? null : $target;
        return $this;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function addChild(NavigationItem|array $child): self
    {
        $child = is_array($child) ? self::fromArray($child) : $child;
        $this->children[] = $child;
        return $this;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    public function setScope(string $scope): self
    {
        $scope = trim($scope);
        $this->scope = $scope === '' ? 'frontend' : $scope;
        return $this;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * @return array<int, NavigationItem>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * @param array<int, string> $routeNames
     */
    public function setRouteNames(array $routeNames): self
    {
        $this->routeNames = [];
        foreach ($routeNames as $routeName) {
            $this->addRouteName($routeName);
        }

        return $this;
    }

    public function addRouteName(string $routeName): self
    {
        $routeName = trim($routeName);
        if ($routeName !== '' && !in_array($routeName, $this->routeNames, true)) {
            $this->routeNames[] = $routeName;
        }

        return $this;
    }

    /**
     * @return array{
     *     key:string,
     *     label:string|null,
     *     href:string|null,
     *     parent:string|null,
     *     icon:string|null,
     *     type:string,
     *     target:string|null,
     *     meta:array<string, mixed>,
     *     order:int,
     *     visible:bool,
     *     scope:string,
     *     routeNames:array<int, string>,
     *     children:array<int, array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'href' => $this->href,
            'parent' => $this->parent,
            'icon' => $this->icon,
            'type' => $this->type,
            'target' => $this->target,
            'meta' => $this->meta,
            'order' => $this->order,
            'visible' => $this->visible,
            'scope' => $this->scope,
            'routeNames' => $this->routeNames,
            'children' => array_map(
                static fn(NavigationItem $child): array => $child->toArray(),
                $this->children
            ),
        ];
    }
}
