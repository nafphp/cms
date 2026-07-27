<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use src\Enums\Permission;
use src\Page\PageFactory;
use src\Page\PageBuilderRenderer;
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
                    'aside' => ['type' => 'chart', 'props' => ['title' => 'Metrics']],
                ],
            ], [
                'id' => 'default',
                'view' => 'page.templates.default',
                'blocks' => [
                    ['id' => 'site_header', 'label' => 'Header'],
                    ['id' => 'main', 'label' => 'Main'],
                    ['id' => 'aside', 'label' => 'Aside'],
                ],
            ]);

        $blocks = $page->pageLayout()?->blocks() ?? [];

        $this->assertSame('page.blocks.header', $blocks['site_header']->template()->view());
        $this->assertSame('page.blocks.text', $blocks['main']->template()->view());
        $this->assertSame('page.blocks.chart', $blocks['aside']->template()->view());
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
            'id' => 'default',
            'view' => 'page.templates.default',
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

        $blocks = $page->pageLayout()?->blocks() ?? [];

        $this->assertSame('page.blocks.header', $blocks['site_header']->template()->view());
        $this->assertSame('page.blocks.empty', $blocks['hero']->template()->view());
        $this->assertSame('page.blocks.empty', $blocks['main']->template()->view());
        $this->assertSame('page.blocks.footer', $blocks['site_footer']->template()->view());
    }

    public function testCustomTemplateUsesStoredMetaLayoutBlocks(): void
    {
        $page = (new PageFactory())->fromStoredRow([
            'id' => 15,
            'path' => '/custom',
            'title' => 'Custom',
            'language' => 'en',
            'status' => 'published',
            'blocks' => [
                'slot_intro' => ['type' => 'heading', 'props' => ['text' => 'Hello']],
                'slot_body' => ['type' => 'text', 'props' => ['text' => 'Body']],
            ],
            'meta' => [
                'custom_layout' => [
                    ['id' => 'slot_intro', 'label' => 'Intro', 'region' => 'header', 'size' => 'small', 'position' => 'center', 'maxHeight' => '480', 'overflow' => 'auto', 'surface' => 'soft'],
                    ['id' => 'slot_body', 'label' => 'Body', 'region' => 'main', 'size' => 'large'],
                ],
            ],
        ], [
            'id' => 'custom',
            'kind' => 'custom',
            'view' => 'page.templates.custom',
            'blocks' => [],
        ]);

        $blocks = $page->pageLayout()?->blocks() ?? [];

        self::assertSame(['slot_intro', 'slot_body'], array_keys($blocks));
        self::assertSame('page.blocks.heading', $blocks['slot_intro']->template()->view());
        self::assertSame('header', $blocks['slot_intro']->data()['region']);
        self::assertSame('center', $blocks['slot_intro']->data()['position']);
        self::assertSame('480', $blocks['slot_intro']->data()['maxHeight']);
        self::assertSame('auto', $blocks['slot_intro']->data()['overflow']);
        self::assertSame('soft', $blocks['slot_intro']->data()['surface']);
        self::assertSame('large', $blocks['slot_body']->data()['size']);
    }

    public function testStaticTemplateCanUseStoredCustomLayoutBlocks(): void
    {
        $page = (new PageFactory())->fromStoredRow([
            'id' => 16,
            'path' => '/landing',
            'title' => 'Landing',
            'language' => 'en',
            'status' => 'published',
            'blocks' => [
                'site_header' => ['type' => 'header', 'props' => []],
                'slot_extra' => ['type' => 'text', 'props' => ['text' => 'Extra']],
            ],
            'meta' => [
                'custom_layout' => [
                    ['id' => 'site_header', 'label' => 'Site header', 'region' => 'header', 'size' => 'full', 'defaultContentType' => 'header'],
                    ['id' => 'slot_extra', 'label' => 'Extra', 'region' => 'main', 'size' => 'medium', 'defaultContentType' => 'text'],
                ],
            ],
        ], [
            'id' => 'landing',
            'kind' => 'static',
            'view' => 'page.templates.landing',
            'blocks' => [
                ['id' => 'site_header', 'label' => 'Site header'],
                ['id' => 'hero', 'label' => 'Hero'],
            ],
        ]);

        $blocks = $page->pageLayout()?->blocks() ?? [];

        self::assertSame(['site_header', 'slot_extra'], array_keys($blocks));
        self::assertSame('page.blocks.text', $blocks['slot_extra']->template()->view());
        self::assertSame('main', $blocks['slot_extra']->data()['region']);
    }

    public function testPublishedRenderUsesTheSameNormalizedNestedPayloadAsPreviewLoading(): void
    {
        $page = (new PageFactory())->fromStoredRow([
            'id' => 17,
            'path' => '/nested',
            'title' => 'Nested',
            'language' => 'en',
            'status' => 'published',
            'blocks' => [
                'main' => [
                    'type' => 'section',
                    'props' => [
                        'width' => 'default',
                        'padding' => 'medium',
                        'tone' => 'none',
                        'children' => [[
                            'type' => 'text',
                            'props' => [
                                'text' => 'Normalized public copy',
                                'tone' => 'body',
                                'align' => 'left',
                                'verticalAlign' => 'top',
                            ],
                        ]],
                    ],
                ],
            ],
            'meta' => [
                'version' => 1,
                'custom_layout' => [[
                    'id' => 'main',
                    'label' => 'Main',
                    'region' => 'main',
                    'size' => 'large',
                    'accepts' => ['section'],
                    'defaultContentType' => 'section',
                ]],
            ],
        ], [
            'id' => 'custom',
            'kind' => 'custom',
            'view' => 'page.templates.custom',
            'blocks' => [],
        ]);

        $html = (new PageBuilderRenderer())->render($page);

        self::assertStringContainsString('Normalized public copy', $html);
        self::assertSame('page.blocks.section', $page->pageLayout()?->blocks()['main']->template()->view());
    }
}
