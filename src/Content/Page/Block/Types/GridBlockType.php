<?php

declare(strict_types=1);

namespace src\Content\Page\Block\Types;

use src\Content\Page\Block\AbstractBlockType;

final class GridBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'grid';
    }

    public function label(): string
    {
        return 'Grid';
    }

    public function description(): string
    {
        return 'Arrange repeated child blocks in a responsive grid.';
    }

    public function regions(): array
    {
        return ['hero', 'main', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Grid';
    }

    public function defaults(): array
    {
        return [
            'columns' => 3,
            'gap' => 'medium',
            'children' => [],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'columns', 'label' => 'Columns', 'type' => 'number', 'min' => 2, 'max' => 4, 'step' => 1],
            ['name' => 'gap', 'label' => 'Gap', 'type' => 'select', 'options' => ['small', 'medium', 'large']],
            ['name' => 'children', 'label' => 'Children', 'type' => 'children'],
        ];
    }
}
