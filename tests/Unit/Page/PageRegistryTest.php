<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Page\PageRegistry;
use PHPUnit\Framework\TestCase;

final class PageRegistryTest extends TestCase
{
    public function testRegistryFindsPageByIdAndPath(): void
    {
        $page = Page::create('custom.about')
            ->setPath('/about')
            ->setContent('About');

        $registry = new PageRegistry();
        $registry->add($page);

        self::assertSame($page, $registry->get('custom.about'));
        self::assertSame($page, $registry->findByPath('/about'));
    }

    public function testRegistryRequiresExplicitReplacement(): void
    {
        $registry = new PageRegistry();
        $registry->add(Page::create('custom.about')->setPath('/about'));

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Page "custom.about" is already registered.');

        $registry->add(Page::create('custom.about')->setPath('/ueber-uns'));
    }

    public function testRegistryCanReplacePage(): void
    {
        $registry = new PageRegistry();
        $registry->add(Page::create('custom.about')->setPath('/about'));

        $replacement = Page::create('custom.about')->setPath('/ueber-uns');
        $registry->add($replacement, replace: true);

        self::assertSame($replacement, $registry->get('custom.about'));
        self::assertSame($replacement, $registry->findByPath('/ueber-uns'));
    }

    public function testRegistryFindsPageByDataHandlerMethod(): void
    {
        $page = Page::create('auth.login')
            ->setPath('/admin/login')
            ->setDataHandler(static fn() => null, 'POST');

        $registry = new PageRegistry();
        $registry->add($page);

        self::assertSame($page, $registry->findByPath('/admin/login', 'POST'));
        self::assertSame('auth.login.post', $registry->dataRouteName($page, $page->dataHandler('POST')));
        self::assertSame($page, $registry->findByDataRouteName('auth.login.post'));
    }

    public function testPublishedStoredHomePageMayReplaceCodeHomePage(): void
    {
        $registry = new PageRegistry();
        $method = new \ReflectionMethod($registry, 'storedPageMayReplaceCodePage');

        $codeHome = Page::create('home')->setPath('/');
        $publishedStoredHome = Page::create('db.page.1')->setPath('/');
        $draftStoredHome = Page::create('db.page.2')
            ->setPath('/')
            ->requirePermission(Permission::PAGES_VIEW);
        $publishedStoredAbout = Page::create('db.page.3')->setPath('/about');

        self::assertTrue($method->invoke($registry, $publishedStoredHome, $codeHome));
        self::assertFalse($method->invoke($registry, $draftStoredHome, $codeHome));
        self::assertFalse($method->invoke($registry, $publishedStoredAbout, $codeHome));
    }

    public function testStoredHomePageKeepsHomeRouteAlias(): void
    {
        $registry = new PageRegistry();
        $storedHome = Page::create('db.page.1')->setPath('/');

        $property = new \ReflectionProperty($registry, 'routeAliases');
        $property->setValue($registry, ['home' => 'db.page.1']);

        $method = new \ReflectionMethod($registry, 'routeAliasesFor');

        self::assertSame(['home'], $method->invoke($registry, $storedHome));
    }
}
