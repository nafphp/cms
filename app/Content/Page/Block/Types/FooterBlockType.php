<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;
use NixCMS\Content\Page\Block\PageBuilderBlockOptions;

final class FooterBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'footer';
    }

    public function label(): string
    {
        return 'Footer';
    }

    public function description(): string
    {
        return 'Site footer with optional navigation and small imprint text.';
    }

    public function regions(): array
    {
        return ['footer'];
    }

    public function previewTitle(): string
    {
        return 'Site footer';
    }

    public function defaults(): array
    {
        $navigationOptions = PageBuilderBlockOptions::navigationOptions();

        return [
            'text' => 'Powered by NixCMS',
            'navigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
            'presentation' => 'compact',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'text', 'label' => 'Footer text', 'type' => 'text'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => PageBuilderBlockOptions::navigationOptions()],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['compact', 'horizontal']],
        ];
    }
}
