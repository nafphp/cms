<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class TextBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text block';
    }

    public function description(): string
    {
        return 'Simple copy for short intro, aside, or CTA text.';
    }

    public function regions(): array
    {
        return ['header', 'hero', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Short text';
    }

    public function defaults(): array
    {
        return [
            'text' => 'A short text block for this position.',
            'tone' => 'body',
            'align' => 'left',
            'verticalAlign' => 'top',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'tone', 'label' => 'Tone', 'type' => 'select', 'options' => ['body', 'lead', 'muted']],
            ['name' => 'align', 'label' => 'Align', 'type' => 'select', 'options' => ['left', 'center', 'right']],
            ['name' => 'verticalAlign', 'label' => 'Vertical align', 'type' => 'select', 'options' => ['top', 'center', 'bottom']],
        ];
    }
}
