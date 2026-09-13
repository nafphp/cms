<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

final class PageNavigationMeta
{
    public function __construct(
        public ?string $label = null,
        public ?string $group = null,
        public ?string $icon = null,
        public int $order = 100,
        public bool $visible = true,
        public ?string $key = null,
        public ?string $parent = null,
        public ?string $href = null,
        public string $scope = 'frontend',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            label: isset($data['label']) ? (string) $data['label'] : null,
            group: isset($data['group']) ? (string) $data['group'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            order: (int) ($data['order'] ?? 100),
            visible: (bool) ($data['visible'] ?? true),
            key: isset($data['key']) ? (string) $data['key'] : null,
            parent: isset($data['parent']) ? (string) $data['parent'] : null,
            href: isset($data['href']) ? (string) $data['href'] : null,
            scope: (string) ($data['scope'] ?? 'frontend'),
        );
    }

    public function parentKey(): ?string
    {
        return $this->parent ?? $this->group;
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'group' => $this->group,
            'icon' => $this->icon,
            'order' => $this->order,
            'visible' => $this->visible,
            'key' => $this->key,
            'parent' => $this->parent,
            'href' => $this->href,
            'scope' => $this->scope,
        ];
    }
}
