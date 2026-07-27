<?php

declare(strict_types=1);

namespace src\Content\Page\Block\Types;

use src\Content\Page\Block\AbstractBlockType;
use src\Content\Page\Block\PageBuilderBlockOptions;

final class AsideBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'aside';
    }

    public function label(): string
    {
        return 'Aside';
    }

    public function description(): string
    {
        return 'Sidebar block for contextual copy and vertical navigation.';
    }

    public function regions(): array
    {
        return ['aside'];
    }

    public function previewTitle(): string
    {
        return 'Aside';
    }

    public function defaults(): array
    {
        $navigationOptions = PageBuilderBlockOptions::navigationOptions();

        return [
            'title' => 'Explore',
            'text' => 'Contextual links and supporting content.',
            'navigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => PageBuilderBlockOptions::navigationOptions()],
        ];
    }
}
