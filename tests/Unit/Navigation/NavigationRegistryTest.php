<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use NixCMS\Navigation\NavigationItem;
use NixCMS\Navigation\NavigationRegistry;
use PHPUnit\Framework\TestCase;

final class NavigationRegistryTest extends TestCase
{
    public function testRegistryStoresNavigationItemsByKey(): void
    {
        $registry = new NavigationRegistry();
        $item = NavigationItem::create('content')
            ->setLabel('Content')
            ->setIcon('content')
            ->setOrder(20);

        $registry->add($item);

        self::assertSame($item, $registry->get('content'));
        self::assertSame(['content' => $item], $registry->all());
    }

    public function testRegistryRequiresExplicitReplacement(): void
    {
        $registry = new NavigationRegistry();
        $registry->add(NavigationItem::create('content')->setLabel('Content'));

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Navigation item "content" is already registered.');

        $registry->add(NavigationItem::create('content')->setLabel('Articles'));
    }

    public function testRegistryAcceptsArrayItemsForPluginConfiguration(): void
    {
        $registry = new NavigationRegistry();
        $registry->add([
            'key' => 'plugin.reports',
            'label' => 'Reports',
            'parent' => 'content',
            'href' => '/admin/reports',
            'scope' => 'admin',
            'routeNames' => ['admin.reports'],
        ]);

        self::assertSame([
            'key' => 'plugin.reports',
            'label' => 'Reports',
            'href' => '/admin/reports',
            'parent' => 'content',
            'icon' => null,
            'order' => 100,
            'visible' => true,
            'scope' => 'admin',
            'routeNames' => ['admin.reports'],
        ], $registry->get('plugin.reports')?->toArray());
    }

    public function testNavigationItemDefaultsToFrontendScopeAndCanSwitchScope(): void
    {
        $item = NavigationItem::create('docs');

        self::assertSame('frontend', $item->toArray()['scope']);

        $item->setScope('admin');

        self::assertSame('admin', $item->toArray()['scope']);
    }
}
