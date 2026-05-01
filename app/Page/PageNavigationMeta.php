<?php

declare(strict_types=1);

namespace NixCMS\Page;

final class PageNavigationMeta
{
    public function __construct(
        public ?string $label = null,
        public ?string $group = null,
        public ?string $icon = null,
        public int $order = 100,
        public bool $visible = true,
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
        );
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'group' => $this->group,
            'icon' => $this->icon,
            'order' => $this->order,
            'visible' => $this->visible,
        ];
    }
}
