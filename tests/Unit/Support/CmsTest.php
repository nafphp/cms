<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use InvalidArgumentException;
use Naf\CMS\Content\Navigation\NavigationContentType;
use Naf\CMS\Content\Page\Block\BlockTypeRegistry;
use Naf\CMS\Enums\Scope;
use Naf\CMS\Navigation\NavigationItem;
use Naf\CMS\Settings\SettingField;
use Naf\CMS\Settings\SettingSection;
use Naf\CMS\Support\Cms;
use Naf\CMS\Visualization\VisualizationRegistry;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CmsTest extends TestCase
{
    public function testCmsThrowsForUnsupportedContentType(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No content type registered for "stdClass". Register one through cms()->contentTypes()->add(...).');

        $cms = new Cms();
        $cms->content(stdClass::class);
    }

    public function testCmsExposesVisualizationRegistry(): void
    {
        $cms = new Cms();

        self::assertInstanceOf(VisualizationRegistry::class, $cms->visualizations());
        self::assertSame($cms->visualizations(), $cms->visualizations());
    }

    public function testCmsExposesPageBuilderBlockTypeRegistry(): void
    {
        $cms = new Cms();

        self::assertInstanceOf(BlockTypeRegistry::class, $cms->blockTypes());
        self::assertSame($cms->blockTypes(), $cms->blockTypes());
    }

    public function testCmsLoadsDefaultFrontendNavigation(): void
    {
        $cms = $this->cmsWithNavigation();
        $cms->navigation()->addMany(require BASE_PATH . '/src/navigation.php');
        $navigation = $cms->navigation()->get('main');

        self::assertNotNull($navigation);
        self::assertSame('Main navigation', $navigation->title());
        self::assertSame(['Home', 'Articles'], array_column($navigation->toArray()['items'], 'label'));
    }

    public function testNavigationApiRegistersMetadataItems(): void
    {
        $cms = $this->cmsWithNavigation();

        $cms->navigation()->add(
            NavigationItem::create('admin.example')
                ->setScope('admin')
                ->setLabel('Example'),
        );

        self::assertSame('Example', $cms->navigation()->item('admin.example')?->toArray()['label']);
    }

    public function testScopedNavigationApiRegistersAdminMetadataItems(): void
    {
        $cms = $this->cmsWithNavigation();

        $cms->scope(Scope::ADMIN)->navigation()->add(
            NavigationItem::create('admin.scoped')
                ->setLabel('Scoped Admin'),
        );

        $item = $cms->scope(Scope::ADMIN)->navigation()->item('admin.scoped');

        self::assertSame('Scoped Admin', $item?->toArray()['label']);
        self::assertSame('admin', $item?->scope());
        self::assertNull($cms->scope(Scope::FRONTEND)->navigation()->item('admin.scoped'));
    }

    public function testScopeDefaultsToFrontend(): void
    {
        $cms = $this->cmsWithNavigation();

        $cms->scope()->navigation()->add(
            NavigationItem::create('frontend.scoped')
                ->setLabel('Scoped Frontend'),
        );

        $item = $cms->scope(Scope::FRONTEND)->navigation()->item('frontend.scoped');

        self::assertSame('Scoped Frontend', $item?->toArray()['label']);
        self::assertSame('frontend', $item?->scope());
        self::assertNull($cms->scope(Scope::ADMIN)->navigation()->item('frontend.scoped'));
    }

    public function testScopedCmsPassesThroughApisWithoutScopeSupport(): void
    {
        $cms = new Cms();

        self::assertSame($cms->pages(), $cms->scope(Scope::ADMIN)->pages());
    }

    public function testSettingsApiRegistersSettingMetadata(): void
    {
        $cms = new Cms();

        $cms->settings()
            ->add(SettingSection::create('example', 'Example'))
            ->addField('example', SettingField::text('example.title', 'Title'));

        self::assertSame('Title', $cms->settings()->field('example.title')?->label());
    }

    private function cmsWithNavigation(): Cms
    {
        $cms = new Cms();
        $cms->contentTypes()->add(new NavigationContentType());

        return $cms;
    }
}
