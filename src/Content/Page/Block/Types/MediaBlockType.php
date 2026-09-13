<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class MediaBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Media';
    }

    public function description(): string
    {
        return 'Image or visual asset with alt text and optional caption.';
    }

    public function regions(): array
    {
        return ['hero', 'main', 'aside'];
    }

    public function previewTitle(): string
    {
        return 'Media';
    }

    public function defaults(): array
    {
        return [
            'src' => '',
            'alt' => '',
            'caption' => '',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'src', 'label' => 'Image', 'type' => 'media', 'altTarget' => 'alt'],
            ['name' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
        ];
    }
}
