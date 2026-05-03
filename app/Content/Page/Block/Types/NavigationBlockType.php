<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;
use NixCMS\Content\Page\Block\PageBuilderBlockOptions;

final class NavigationBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'navigation';
    }

    public function label(): string
    {
        return 'Navigation';
    }

    public function description(): string
    {
        return 'Render a stored navigation tree in a matching page region.';
    }

    public function regions(): array
    {
        return ['header', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Main navigation';
    }

    public function defaults(): array
    {
        $navigationOptions = PageBuilderBlockOptions::navigationOptions();

        return [
            'identifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
            'presentation' => 'horizontal',
            'language' => 'current',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'identifier', 'label' => 'Navigation', 'type' => 'select', 'options' => PageBuilderBlockOptions::navigationOptions()],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['horizontal', 'vertical', 'compact']],
            ['name' => 'language', 'label' => 'Language', 'type' => 'select', 'options' => ['current', 'de', 'en', 'fr', 'es', 'it']],
        ];
    }
}
