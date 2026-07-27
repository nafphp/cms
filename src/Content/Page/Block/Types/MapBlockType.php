<?php

declare(strict_types=1);

namespace src\Content\Page\Block\Types;

use src\Content\Page\Block\AbstractBlockType;

final class MapBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'map';
    }

    public function label(): string
    {
        return 'Map';
    }

    public function description(): string
    {
        return 'Embed a map from an address, place query, or custom map embed URL.';
    }

    public function regions(): array
    {
        return ['main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Map';
    }

    public function defaults(): array
    {
        return [
            'query' => '',
            'embedUrl' => '',
            'title' => 'Find us',
            'caption' => '',
            'height' => 360,
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'query', 'label' => 'Address or place', 'type' => 'text'],
            ['name' => 'embedUrl', 'label' => 'Custom embed URL', 'type' => 'text'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['name' => 'height', 'label' => 'Height', 'type' => 'number', 'min' => 220, 'max' => 760, 'step' => 20],
        ];
    }
}
