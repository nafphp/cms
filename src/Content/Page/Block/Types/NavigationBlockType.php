<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;
use NixPHP\CMS\Content\Page\Block\PageBuilderBlockOptions;

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
        return ['header', 'main', 'aside', 'footer'];
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
            'linkStyle' => 'blank',
            'linkTheme' => 'default',
            'language' => 'current',
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'identifier', 'label' => 'Navigation', 'type' => 'select', 'options' => PageBuilderBlockOptions::navigationOptions()],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['horizontal', 'vertical', 'compact']],
            ['name' => 'linkStyle', 'label' => 'Link style', 'type' => 'select', 'options' => [
                ['label' => 'Blank', 'value' => 'blank'],
                ['label' => 'Underline', 'value' => 'underline'],
                ['label' => 'Pill', 'value' => 'pill'],
                ['label' => 'Rounded pill', 'value' => 'rounded-pill'],
                ['label' => 'Filled', 'value' => 'filled'],
                ['label' => 'Filled rounded', 'value' => 'filled-rounded'],
            ]],
            ['name' => 'linkTheme', 'label' => 'Link theme', 'type' => 'select', 'options' => [
                ['label' => 'Default', 'value' => 'default'],
                ['label' => 'Accent', 'value' => 'accent'],
                ['label' => 'Surface', 'value' => 'surface'],
            ]],
            ['name' => 'language', 'label' => 'Language', 'type' => 'select', 'options' => ['current', 'de', 'en', 'fr', 'es', 'it']],
            $this->surfaceField(),
        ];
    }
}
