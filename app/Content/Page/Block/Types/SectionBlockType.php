<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;

final class SectionBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'section';
    }

    public function label(): string
    {
        return 'Section';
    }

    public function description(): string
    {
        return 'Wrapper for one or more child blocks with spacing, width, and surface controls.';
    }

    public function regions(): array
    {
        return ['hero', 'header', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Section';
    }

    public function defaults(): array
    {
        return [
            'width' => 'default',
            'padding' => 'medium',
            'tone' => 'none',
            'children' => [],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'width', 'label' => 'Width', 'type' => 'select', 'options' => ['default', 'narrow', 'wide', 'full']],
            ['name' => 'padding', 'label' => 'Padding', 'type' => 'select', 'options' => ['none', 'small', 'medium', 'large']],
            ['name' => 'tone', 'label' => 'Surface', 'type' => 'select', 'options' => ['none', 'soft', 'card', 'accent']],
            ['name' => 'children', 'label' => 'Children', 'type' => 'children'],
        ];
    }
}
