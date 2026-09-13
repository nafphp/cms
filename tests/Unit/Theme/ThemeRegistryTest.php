<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use Naf\CMS\Theme\Theme;
use Naf\CMS\Theme\ThemeRegistry;
use PHPUnit\Framework\TestCase;

final class ThemeRegistryTest extends TestCase
{
    public function testDefaultThemeProvidesCoreSurfaces(): void
    {
        $registry = new ThemeRegistry();

        self::assertSame('default', $registry->default()->id());
        self::assertSame([
            ['value' => 'none', 'label' => 'None'],
            ['value' => 'soft', 'label' => 'Soft'],
            ['value' => 'card', 'label' => 'Card'],
            ['value' => 'accent', 'label' => 'Accent'],
        ], $registry->default()->surfaceOptions());
    }

    public function testThemeRegistersAssetsAndCustomSurfaces(): void
    {
        $theme = Theme::create('Brand Theme')
            ->setLabel('Brand')
            ->addStylesheet('/css/themes/brand.css')
            ->addScript('/js/themes/brand.js', ['type' => 'module'])
            ->addSurface('shadow-card', 'Shadow Card');

        $registry = new ThemeRegistry();
        $registry->add($theme);

        self::assertSame('brand-theme', $theme->id());
        self::assertSame('theme-brand-theme', $theme->className());
        self::assertSame($theme, $registry->resolve('brand-theme'));
        self::assertSame([['value' => 'shadow-card', 'label' => 'Shadow Card']], $theme->surfaceOptions());
        self::assertSame('/css/themes/brand.css', $theme->stylesheets()[0]['href']);
        self::assertSame('/js/themes/brand.js', $theme->scripts()[0]['src']);
    }
}
