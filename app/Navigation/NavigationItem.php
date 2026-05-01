<?php

declare(strict_types=1);

namespace NixCMS\Navigation;

final class NavigationItem
{
    /** @var array<int, string> */
    private array $routeNames = [];
    private ?string $label = null;
    private ?string $href = null;
    private ?string $parent = null;
    private ?string $icon = null;
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
        $item->order = (int) ($data['order'] ?? 100);
        $item->visible = (bool) ($data['visible'] ?? true);
        $item->scope = (string) ($data['scope'] ?? 'frontend');

        if (is_array($data['routeNames'] ?? null)) {
            $item->setRouteNames(array_map('strval', $data['routeNames']));
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
     *     order:int,
     *     visible:bool,
     *     scope:string,
     *     routeNames:array<int, string>
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
            'order' => $this->order,
            'visible' => $this->visible,
            'scope' => $this->scope,
            'routeNames' => $this->routeNames,
        ];
    }
}
