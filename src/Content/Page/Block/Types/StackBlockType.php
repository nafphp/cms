<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class StackBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'stack';
    }

    public function label(): string
    {
        return 'Stack';
    }

    public function description(): string
    {
        return 'Place child blocks under each other with controlled gap and alignment.';
    }

    public function regions(): array
    {
        return ['hero', 'header', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Stacked blocks';
    }

    public function defaults(): array
    {
        return [
            'gap' => 'medium',
            'align' => 'stretch',
            'children' => [],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'gap', 'label' => 'Gap', 'type' => 'select', 'options' => ['none', 'small', 'medium', 'large']],
            ['name' => 'align', 'label' => 'Align', 'type' => 'select', 'options' => ['stretch', 'start', 'center', 'end']],
            ['name' => 'children', 'label' => 'Children', 'type' => 'children'],
        ];
    }
}
