<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use NixCMS\Enums\Permission;
use NixCMS\Page\PageFactory;
use PHPUnit\Framework\TestCase;

final class PageFactoryTest extends TestCase
{
    public function testStoredBlocksUseRegisteredProductionViews(): void
    {
        $page = (new PageFactory())->fromStoredRow([
            'id' => 42,
            'path' => '/about',
            'title' => 'About',
            'language' => 'en',
            'status' => 'published',
            'layout' => 'layouts.page',
            'blocks' => [
                'site_header' => ['type' => 'header', 'props' => []],
                'main' => ['type' => 'text', 'props' => ['text' => 'Hello']],
            ],
        ], [
            'id' => 'public',
            'view' => 'page.templates.public',
            'blocks' => [
                ['id' => 'site_header', 'label' => 'Header'],
                ['id' => 'main', 'label' => 'Main'],
            ],
        ]);

        $blocks = $page->template()?->blocks() ?? [];

        $this->assertSame('page.blocks.header', $blocks['site_header']->view());
        $this->assertSame('page.blocks.text', $blocks['main']->view());
    }

    public function testDraftStoredPageRequiresPageViewPermission(): void
    {
        $page = (new PageFactory())->fromStoredRow([
            'id' => 7,
            'path' => '/draft',
            'title' => 'Draft',
            'language' => 'en',
            'status' => 'draft',
            'blocks' => [],
        ], [
            'id' => 'public',
            'view' => 'page.templates.public',
            'blocks' => [],
        ]);

        $this->assertContains(Permission::PAGES_VIEW, $page->requiredPermissions());
    }

    public function testEmptyStoredBlocksDoNotRenderBuilderPlaceholder(): void
    {
        $page = (new PageFactory())->fromStoredRow([
            'id' => 9,
            'path' => '/landing',
            'title' => 'Landing',
            'language' => 'de',
            'status' => 'published',
            'blocks' => [
                'hero' => null,
                'main' => null,
            ],
        ], [
            'id' => 'landing',
            'view' => 'page.templates.landing',
            'blocks' => [
                ['id' => 'site_header', 'label' => 'Site header', 'preview' => 'navigation'],
                ['id' => 'hero', 'label' => 'Hero', 'preview' => 'hero'],
                ['id' => 'main', 'label' => 'Main content'],
                ['id' => 'site_footer', 'label' => 'Site footer', 'preview' => 'footer'],
            ],
        ]);

        $blocks = $page->template()?->blocks() ?? [];

        $this->assertSame('page.blocks.header', $blocks['site_header']->view());
        $this->assertSame('page.blocks.empty', $blocks['hero']->view());
        $this->assertSame('page.blocks.empty', $blocks['main']->view());
        $this->assertSame('page.blocks.footer', $blocks['site_footer']->view());
    }
}
