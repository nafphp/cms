<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class ColumnsBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'columns';
    }

    public function label(): string
    {
        return 'Columns';
    }

    public function description(): string
    {
        return 'Place child blocks next to each other, stacking automatically on small screens.';
    }

    public function regions(): array
    {
        return ['hero', 'header', 'main', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Columns';
    }

    public function defaults(): array
    {
        return [
            'columns'  => 2,
            'gap'      => 'medium',
            'align'    => 'start',
            'children' => [],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'columns', 'label' => 'Columns', 'type' => 'number', 'min' => 2, 'max' => 4, 'step' => 1],
            ['name' => 'gap', 'label' => 'Gap', 'type' => 'select', 'options' => ['small', 'medium', 'large']],
            ['name' => 'align', 'label' => 'Align', 'type' => 'select', 'options' => ['start', 'center', 'end', 'stretch']],
            ['name' => 'children', 'label' => 'Children', 'type' => 'children'],
        ];
    }
}
