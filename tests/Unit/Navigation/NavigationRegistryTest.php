<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use Naf\CMS\Navigation\NavigationItem;
use Naf\CMS\Navigation\Navigation;
use Naf\CMS\Navigation\NavigationRegistry;
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
            'type' => 'url',
            'target' => null,
            'meta' => [],
            'order' => 100,
            'visible' => true,
            'scope' => 'admin',
            'routeNames' => ['admin.reports'],
            'children' => [],
        ], $registry->get('plugin.reports')?->toArray());
    }

    public function testNavigationItemDefaultsToFrontendScopeAndCanSwitchScope(): void
    {
        $item = NavigationItem::create('docs');

        self::assertSame('frontend', $item->toArray()['scope']);
        self::assertSame('frontend', $item->scope());

        $item->setScope('admin');

        self::assertSame('admin', $item->toArray()['scope']);
        self::assertSame('admin', $item->scope());
    }

    public function testRegistryCanReturnItemsForAScope(): void
    {
        $registry = new NavigationRegistry();
        $docs = NavigationItem::create('docs')->setLabel('Docs');
        $admin = NavigationItem::create('admin.tools')->setLabel('Tools')->setScope('admin');

        $registry->addMany([$docs, $admin]);

        self::assertSame(['docs' => $docs], $registry->allForScope('frontend'));
        self::assertSame(['admin.tools' => $admin], $registry->allForScope('admin'));
    }

    public function testRegistryStoresScopedNavigationObjects(): void
    {
        $registry = new NavigationRegistry();
        $navigation = Navigation::create('main', 'Main navigation')
            ->addItem(
                NavigationItem::create('home')
                    ->setLabel('Home')
                    ->setHref('/')
                    ->addChild(NavigationItem::create('docs')->setLabel('Docs')->setHref('/docs'))
            );

        $registry->addNavigation($navigation);

        self::assertSame($navigation, $registry->getNavigation('main'));
        self::assertSame(['frontend:main:' => $navigation], $registry->allNavigationsForScope('frontend'));
        self::assertSame('docs', $navigation->toArray()['items'][0]['children'][0]['key']);
    }

    public function testRegistryFallsBackFromLanguageSpecificNavigationToDefault(): void
    {
        $registry = new NavigationRegistry();
        $navigation = Navigation::create('main');

        $registry->addNavigation($navigation);

        self::assertSame($navigation, $registry->getNavigation('main', language: 'de'));
    }
}
