<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use Naf\CMS\Content\BlockViewRegistry;
use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Examples\BlockType\ExampleQuoteBlockType;
use Naf\Core\Config;
use Psr\Log\LoggerInterface;
use Tests\Support\CmsTestCase;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\log;

final class BlockViewRegistryTest extends CmsTestCase
{
    /**
     * The example file registers its type on cms() when it is first loaded.
     * Load it before setUp() swaps in a fresh registry; otherwise that
     * registration is lost and ExamplesSmokeTest, which loads the same file,
     * finds nothing.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once BASE_PATH . '/examples/03-block-type/block_type.php';
    }

    public function testDefaultsExposeProductionBlockViews(): void
    {
        $registry = new BlockViewRegistry();

        self::assertSame('page.blocks.header', $registry->viewFor('header'));
        self::assertSame('page.blocks.chart', $registry->viewFor('chart'));
        self::assertSame('page.blocks.article_title', $registry->viewFor('article-title'));
        self::assertSame('page.blocks.article_related', $registry->viewFor('article-related'));
        self::assertSame('page.blocks.section', $registry->viewFor('section'));
        self::assertSame('page.blocks.columns', $registry->viewFor('columns'));
        self::assertSame('page.blocks.text', $registry->viewFor(' text '));
        self::assertSame('page.blocks.chart', $registry->all()['chart']);
    }

    public function testAddRegistersPluginBlockView(): void
    {
        $registry = new BlockViewRegistry();

        $registry->add('MyCustom', 'page.blocks.mycustom');

        self::assertSame('page.blocks.mycustom', $registry->declaredView('mycustom'));
    }

    public function testAliasesUseExplicitDefaultViews(): void
    {
        $registry = new BlockViewRegistry();

        self::assertSame('page.blocks.form', $registry->viewFor('contact-form'));
        self::assertSame('page.blocks.text', $registry->viewFor('aside'));
    }

    public function testUnknownBlockTypeFallsBackToPlaceholder(): void
    {
        self::assertSame('page.blocks.placeholder', (new BlockViewRegistry())->viewFor('unknown'));
    }

    public function testConventionAndExplicitOverrideAndRemoval(): void
    {
        $type = new class extends AbstractBlockType {
            public function id(): string
            {
                return 'example-quote';
            }

            public function label(): string
            {
                return 'Quote';
            }

            public function regions(): array
            {
                return ['main'];
            }
        };
        cms()->blockTypes()->add($type);
        $registry = cms()->blocks();
        self::assertTrue($registry->has('example-quote'));
        self::assertSame('page.blocks.example_quote', $registry->declaredView('example-quote'));
        $registry->add('example-quote', 'plugin.special');
        self::assertSame('plugin.special', $registry->declaredView('example-quote'));
        $registry->remove('example-quote');
        self::assertSame('page.blocks.example_quote', $registry->declaredView('example-quote'));
        self::assertSame(cms()->blockTypes(), cms()->blockTypes()->remove('example-quote'));
        self::assertFalse($registry->has('example-quote'));
        self::assertNull(cms()->blockTypes()->get('example-quote'));
    }

    public function testConventionViewRendersPluginContentWithoutAMapping(): void
    {
        cms()->blockTypes()->add(new ExampleQuoteBlockType(), replace: true);
        $configuration           = app()->container()->get(Config::class);
        $values                  = $configuration->all();
        $values['view']['paths'] = [BASE_PATH . '/examples/03-block-type/src/views'];
        app()->container()->set(Config::class, new Config($values));

        try {
            $html = \Naf\View\view(cms()->blocks()->viewFor('example_quote'), ['content' => ['props' => ['quote' => 'Plugin quote', 'source' => 'A reader']]]);
            self::assertStringContainsString('Plugin quote', $html);
            self::assertStringContainsString('A reader', $html);
        } finally {
            app()->container()->set(Config::class, $configuration);
        }
    }

    public function testMissingMappingWarnsOncePerType(): void
    {
        $previous = log();
        $logger   = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('No view registered for page block type', ['type' => 'missing']);
        app()->container()->set(LoggerInterface::class, $logger);

        try {
            $registry = new BlockViewRegistry();
            self::assertSame('page.blocks.placeholder', $registry->viewFor('missing'));
            self::assertSame('page.blocks.placeholder', $registry->viewFor('MISSING'));
        } finally {
            app()->container()->set(LoggerInterface::class, $previous);
        }
    }

    public function testDeclaredViewWithoutFileRendersThePlaceholderAndWarnsOnce(): void
    {
        cms()->blockTypes()->add(new class extends AbstractBlockType {
            public function id(): string
            {
                return 'viewless-plugin';
            }

            public function label(): string
            {
                return 'Viewless';
            }

            public function regions(): array
            {
                return ['main'];
            }
        });

        $previous = log();
        $logger   = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Page block view not found', [
            'type' => 'viewless-plugin',
            'view' => 'page.blocks.viewless_plugin',
        ]);
        app()->container()->set(LoggerInterface::class, $logger);

        try {
            $registry = cms()->blocks();
            self::assertSame('page.blocks.viewless_plugin', $registry->declaredView('viewless-plugin'));
            self::assertSame('page.blocks.placeholder', $registry->viewFor('viewless-plugin'));
            self::assertSame('page.blocks.placeholder', $registry->viewFor('viewless-plugin'));
            self::assertSame('page.blocks.text', $registry->viewFor('text'));

            // The page still renders: the block becomes a placeholder instead of a 500.
            $html = \Naf\View\view($registry->viewFor('viewless-plugin'), ['label' => 'Viewless', 'preview' => 'block', 'size' => 'medium']);
            self::assertStringContainsString('Viewless', $html);
        } finally {
            app()->container()->set(LoggerInterface::class, $previous);
        }
    }
}
