<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class HeroBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function description(): string
    {
        return 'Large first-screen block with headline, copy, CTA, and optional image.';
    }

    public function regions(): array
    {
        return ['hero', 'header', 'main'];
    }

    public function previewTitle(): string
    {
        return 'Hero section';
    }

    public function defaults(): array
    {
        return [
            'eyebrow'     => 'NafCMS',
            'title'       => 'A clear page hero',
            'text'        => 'Use this area for the main promise of the page.',
            'buttonLabel' => 'Get started',
            'buttonUrl'   => '#',
            'image'       => '',
            ...$this->surfaceDefaults('card'),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'buttonLabel', 'label' => 'Button label', 'type' => 'text'],
            ['name' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'text'],
            ['name' => 'image', 'label' => 'Image', 'type' => 'media'],
            $this->surfaceField(),
        ];
    }
}
