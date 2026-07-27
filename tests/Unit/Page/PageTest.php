<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use src\Enums\Permission;
use src\Enums\Role;
use src\Page\Layout;
use src\Page\Page;
use src\Page\Template;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testPageStoresCoreRouteAndAccessMetadata(): void
    {
        $page = Page::create('admin.dashboard')
            ->setPath('admin')
            ->setTitle('Dashboard')
            ->setLayout('admin')
            ->requireAuthentication()
            ->requireRole(Role::ADMIN)
            ->requirePermission(Permission::ADMIN_ACCESS);

        self::assertSame('admin.dashboard', $page->id());
        self::assertSame('/admin', $page->path());
        self::assertSame('Dashboard', $page->title());
        self::assertSame('layouts.admin', $page->layout());
        self::assertTrue($page->requiresAuthentication());
        self::assertSame([Role::ADMIN], $page->requiredRoles());
        self::assertSame([Permission::ADMIN_ACCESS], $page->requiredPermissions());
    }

    public function testMetaIsMutableThroughMetaHelper(): void
    {
        $page = Page::create('custom.about');

        $page->meta()->seo->description = 'About NixCMS';
        $page->meta()->navigation->label = 'About';
        $page->meta()->theme->accent = '#64748b';
        $page->meta()->theme->headerBackground = '#020617';
        $page->meta()->theme->headerBackgroundTransparent = false;
        $page->meta()->theme->footerBackground = '#111827';
        $page->meta()->theme->footerBackgroundTransparent = true;
        $page->meta()->theme->contentWidth = 'wide';
        $page->meta()->theme->headerContentWidthIncludesChrome = true;
        $page->meta()->theme->headerBackgroundWidthIncludesChrome = true;
        $page->meta()->theme->footerContentWidthIncludesChrome = false;
        $page->meta()->theme->footerBackgroundWidthIncludesChrome = false;

        self::assertSame('About NixCMS', $page->meta()->seo->description);
        self::assertSame('About', $page->meta()->navigation->label);
        self::assertSame('#64748b', $page->meta()->theme->accent);
        self::assertSame('#020617', $page->meta()->theme->headerBackground);
        self::assertFalse($page->meta()->theme->headerBackgroundTransparent);
        self::assertSame('#111827', $page->meta()->theme->footerBackground);
        self::assertTrue($page->meta()->theme->footerBackgroundTransparent);
        self::assertSame('wide', $page->meta()->theme->contentWidth);
        self::assertTrue($page->meta()->theme->headerContentWidthIncludesChrome);
        self::assertTrue($page->meta()->theme->headerBackgroundWidthIncludesChrome);
        self::assertFalse($page->meta()->theme->footerContentWidthIncludesChrome);
        self::assertFalse($page->meta()->theme->footerBackgroundWidthIncludesChrome);
    }

    public function testPageCanRegisterDataHandlerWithPermission(): void
    {
        $handler = static fn() => null;
        $page = Page::create('admin.users.create')
            ->setPath('/admin/users/create')
            ->setDataHandler($handler, 'post', Permission::USERS_CREATE);

        $dataHandler = $page->dataHandler('POST');

        self::assertNotNull($dataHandler);
        self::assertSame('POST', $dataHandler->method());
        self::assertSame($handler, $dataHandler->handler);
        self::assertSame(Permission::USERS_CREATE, $dataHandler->permission);
    }

    public function testPageCanDefinePageLayoutByTemplateObject(): void
    {
        $template = Template::create('landing')->setParent('page');

        $page = Page::create('frontend.home')
            ->setPath('/')
            ->setLayout($template);

        self::assertSame($template, $page->pageLayout()?->template());
        self::assertSame('page.templates.landing', $page->pageLayout()?->templateView());
        self::assertSame('layouts.page', $page->pageLayout()?->parent());
    }

    public function testPageCanUseLayoutObjectWithTemplateBackedBlocks(): void
    {
        $layout = Layout::create('landing')
            ->addBlock('hero', 'frontend.blocks.hero', ['headline' => 'From template'])
            ->addBlock('main', 'frontend.blocks.main');

        $page = Page::create('frontend.home')
            ->setPath('/')
            ->setLayout($layout);

        self::assertSame($layout, $page->pageLayout());
        self::assertSame('page.templates.landing', $page->pageLayout()?->templateView());
        self::assertSame(['headline' => 'From template'], $page->pageLayout()->blocks()['hero']->data());
        self::assertSame('frontend.blocks.main', $page->pageLayout()->blocks()['main']->template()->view());
    }

    public function testLayoutRejectsDuplicateBlockIds(): void
    {
        $layout = Layout::create('landing')->addBlock('hero', 'frontend.blocks.hero');

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Layout already has a block "hero".');

        $layout->addBlock('hero', 'frontend.blocks.alternate-hero');
    }

    public function testTemplateCanCarryDesignDefaults(): void
    {
        $template = Template::create('public')
            ->setParent('page')
            ->addBodyClass('min-h-screen bg-white')
            ->addStylesheet('/css/nixcms.css')
            ->addScript('/js/public.js', ['defer' => true]);

        self::assertSame('layouts.page', $template->parent());
        self::assertSame(['min-h-screen', 'bg-white'], $template->bodyClasses());
        self::assertSame([
            ['href' => '/css/nixcms.css', 'attributes' => []],
        ], $template->stylesheets());
        self::assertSame([
            ['src' => '/js/public.js', 'attributes' => ['defer' => true]],
        ], $template->scripts());
    }

    public function testPageCanCarryDocumentDataWithoutSeparateDocumentObject(): void
    {
        $page = Page::create('frontend.home')
            ->setPath('/')
            ->setLanguage('DE')
            ->setHtmlAttribute('data-theme', 'default')
            ->setBodyAttribute('data-page', 'home')
            ->addBodyClass('min-h-screen bg-white')
            ->addStylesheet('/css/nixcms.css', ['media' => 'screen'])
            ->addScript('/js/home.js', ['defer' => true]);

        self::assertSame('de', $page->language());
        self::assertSame(['data-theme' => 'default'], $page->htmlAttributes());
        self::assertSame(['data-page' => 'home'], $page->bodyAttributes());
        self::assertSame(['min-h-screen', 'bg-white'], $page->bodyClasses());
        self::assertSame([
            ['href' => '/css/nixcms.css', 'attributes' => ['media' => 'screen']],
        ], $page->stylesheets());
        self::assertSame([
            ['src' => '/js/home.js', 'attributes' => ['defer' => true]],
        ], $page->scripts());
    }
}
