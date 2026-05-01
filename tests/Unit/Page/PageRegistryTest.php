<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use NixCMS\Page\Page;
use NixCMS\Page\PageRegistry;
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
}
