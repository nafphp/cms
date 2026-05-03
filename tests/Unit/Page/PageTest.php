<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use NixCMS\Enums\Permission;
use NixCMS\Enums\Role;
use NixCMS\Page\Page;
use NixCMS\Page\Template;
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
        $page->meta()->theme->contentWidth = 'wide';
        $page->meta()->theme->contentWidthIncludesChrome = true;
        $page->meta()->theme->contentWidthIncludesChromeBackground = true;

        self::assertSame('About NixCMS', $page->meta()->seo->description);
        self::assertSame('About', $page->meta()->navigation->label);
        self::assertSame('#64748b', $page->meta()->theme->accent);
        self::assertSame('wide', $page->meta()->theme->contentWidth);
        self::assertTrue($page->meta()->theme->contentWidthIncludesChrome);
        self::assertTrue($page->meta()->theme->contentWidthIncludesChromeBackground);
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

    public function testPageCanDefineBuilderTemplateByString(): void
    {
        $page = Page::create('frontend.home')
            ->setPath('/')
            ->setTemplate('landing');

        self::assertSame('page.templates.landing', $page->templateView());
    }

    public function testPageCanUseTemplateObjectWithDefaultBlocks(): void
    {
        $template = (new Template('landing'))
            ->addBlock('hero', 'frontend.blocks.hero', ['headline' => 'From template'])
            ->addBlock('main', 'frontend.blocks.main');

        $page = Page::create('frontend.home')
            ->setPath('/')
            ->setTemplate($template);

        self::assertSame($template, $page->template());
        self::assertSame('page.templates.landing', $page->templateView());
        self::assertSame(['headline' => 'From template'], $page->template()->blocks()['hero']->data());
        self::assertSame('frontend.blocks.main', $page->template()->blocks()['main']->view());
    }

    public function testTemplateRejectsDuplicateBlockIds(): void
    {
        $template = (new Template('landing'))->addBlock('hero', 'frontend.blocks.hero');

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Template already has a block "hero".');

        $template->addBlock('hero', 'frontend.blocks.alternate-hero');
    }

    public function testTemplateCanCarryDesignDefaults(): void
    {
        $template = Template::create('public')
            ->setLayout('page')
            ->addBodyClass('min-h-screen bg-white')
            ->addStylesheet('/css/nixcms.css')
            ->addScript('/js/public.js', ['defer' => true]);

        self::assertSame('layouts.page', $template->layout());
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
