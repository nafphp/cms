<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class EmbedBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'embed';
    }

    public function label(): string
    {
        return 'External embed';
    }

    public function description(): string
    {
        return 'Embed an external page in an iframe when the target allows it.';
    }

    public function regions(): array
    {
        return ['main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'External embed';
    }

    public function defaults(): array
    {
        return [
            'url' => 'https://example.com',
            'title' => 'External content',
            'caption' => '',
            'height' => 420,
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'url', 'label' => 'Embed URL', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['name' => 'height', 'label' => 'Height', 'type' => 'number', 'min' => 220, 'max' => 900, 'step' => 20],
        ];
    }
}
