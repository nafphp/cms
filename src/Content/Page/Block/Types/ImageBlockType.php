<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class ImageBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function description(): string
    {
        return 'Single image block with aspect ratio, alt text, and caption.';
    }

    public function regions(): array
    {
        return ['hero', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Image';
    }

    public function defaults(): array
    {
        return [
            'src' => '',
            'alt' => '',
            'caption' => '',
            'aspect' => 'wide',
            'fit' => 'cover',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'src', 'label' => 'Image', 'type' => 'media', 'altTarget' => 'alt'],
            ['name' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['name' => 'aspect', 'label' => 'Aspect ratio', 'type' => 'select', 'options' => [
                ['label' => 'Wide', 'value' => 'wide'],
                ['label' => 'Square', 'value' => 'square'],
                ['label' => 'Portrait', 'value' => 'portrait'],
                ['label' => 'Original', 'value' => 'original'],
            ]],
            ['name' => 'fit', 'label' => 'Image fit', 'type' => 'select', 'options' => [
                ['label' => 'Cover', 'value' => 'cover'],
                ['label' => 'Contain', 'value' => 'contain'],
            ]],
        ];
    }
}
