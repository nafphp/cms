<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block;

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

    public function defaults(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [];
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
