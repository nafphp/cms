<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class FeatureBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'feature';
    }

    public function label(): string
    {
        return 'Feature card';
    }

    public function description(): string
    {
        return 'Reusable card for feature grids and compact benefit sections.';
    }

    public function regions(): array
    {
        return ['main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Feature';
    }

    public function defaults(): array
    {
        return [
            'icon' => 'N',
            'title' => 'Feature',
            'text' => 'Describe one focused capability.',
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'icon', 'label' => 'Icon label', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            $this->surfaceField(),
        ];
    }
}
