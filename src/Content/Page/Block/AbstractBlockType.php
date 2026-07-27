<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block;

use NixPHP\CMS\Content\Page\Block\BlockTypeInterface;

abstract class AbstractBlockType implements BlockTypeInterface
{
    public function description(): string
    {
        return '';
    }

    public function previewTitle(): string
    {
        return $this->label();
    }

    public function unique(): bool
    {
        return false;
    }

    public function defaults(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function surfaceDefaults(string $surface = 'none'): array
    {
        return ['surface' => $surface];
    }

    /**
     * @return array{name: string, label: string, type: string, options: array<int, string>}
     */
    protected function surfaceField(): array
    {
        return ['name' => 'surface', 'label' => 'Surface', 'type' => 'select', 'options' => ['none', 'soft', 'card', 'accent']];
    }

    public function contentTypes(): array
    {
        return [];
    }

    public function templateKinds(): array
    {
        return [];
    }

    public function toBuilderArray(): array
    {
        $data = [
            'type' => $this->id(),
            'label' => $this->label(),
            'description' => $this->description(),
            'regions' => $this->regions(),
            'previewTitle' => $this->previewTitle(),
            'defaults' => $this->defaults(),
            'unique' => $this->unique(),
        ];

        if ($this->fields() !== []) {
            $data['fields'] = $this->fields();
        }

        if ($this->contentTypes() !== []) {
            $data['contentTypes'] = $this->contentTypes();
        }

        if ($this->templateKinds() !== []) {
            $data['templateKinds'] = $this->templateKinds();
        }

        return $data;
    }
}
