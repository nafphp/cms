<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class CtaBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'Call to action';
    }

    public function description(): string
    {
        return 'Compact conversion strip with text and one action.';
    }

    public function regions(): array
    {
        return ['hero', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Call to action';
    }

    public function defaults(): array
    {
        return [
            'title' => 'Ready to move?',
            'text' => 'Give visitors a direct next step.',
            'buttonLabel' => 'Continue',
            'buttonUrl' => '#',
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'buttonLabel', 'label' => 'Button label', 'type' => 'text'],
            ['name' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'text'],
            $this->surfaceField(),
        ];
    }
}
