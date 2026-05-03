<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;

final class HeadingBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Headline';
    }

    public function description(): string
    {
        return 'Simple title text for hero, intro, or section headings.';
    }

    public function regions(): array
    {
        return ['header', 'hero', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'New heading';
    }

    public function defaults(): array
    {
        return [
            'text' => 'New heading',
            'level' => 'h2',
            'align' => 'left',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'text', 'label' => 'Text', 'type' => 'text'],
            ['name' => 'level', 'label' => 'Level', 'type' => 'select', 'options' => ['h1', 'h2', 'h3']],
            ['name' => 'align', 'label' => 'Align', 'type' => 'select', 'options' => ['left', 'center', 'right']],
        ];
    }
}
