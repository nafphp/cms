<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use NixPHP\CMS\Contracts\ThemeSourceInterface;
use NixPHP\CMS\Support\Cms;
use NixPHP\CMS\Theme\Theme;
use PHPUnit\Framework\TestCase;
use function NixPHP\app;

final class CmsThemeSourceTest extends TestCase
{
    private ThemeSourceInterface $originalSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSource = app()->container()->get(ThemeSourceInterface::class);
    }

    protected function tearDown(): void
    {
        app()->container()->set(ThemeSourceInterface::class, $this->originalSource);
        parent::tearDown();
    }

    public function testRegistryLoadsAReplaceableSourceWithoutDatabaseAccess(): void
    {
        app()->container()->set(ThemeSourceInterface::class, new class implements ThemeSourceInterface {
            public function themes(): array
            {
                return [
                    Theme::create('default')->setLabel('Stored default'),
                    Theme::create('brand')->setLabel('Brand'),
                ];
            }
        });

        $registry = (new Cms())->themes();

        self::assertSame('Stored default', $registry->default()->label());
        self::assertSame('Brand', $registry->resolve('brand')->label());
    }
}
