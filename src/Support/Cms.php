<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use InvalidArgumentException;
use Naf\CMS\Api\ApiRegistry;
use Naf\CMS\Api\ArticleApi;
use Naf\CMS\Api\FormApi;
use Naf\CMS\Api\MediaApi;
use Naf\CMS\Api\NavigationApi;
use Naf\CMS\Content\BlockViewRegistry;
use Naf\CMS\Content\ContentRegistry;
use Naf\CMS\Content\Page\Block\BlockTypeRegistry;
use Naf\CMS\Contracts\ThemeSourceInterface;
use Naf\CMS\Enums\Scope;
use Naf\CMS\Localization\LanguageRegistry;
use Naf\CMS\Models\Article;
use Naf\CMS\Models\Form;
use Naf\CMS\Models\Media;
use Naf\CMS\Models\SiteNavigation;
use Naf\CMS\Page\PageRegistry;
use Naf\CMS\Storage\EntityStorage;
use Naf\CMS\Theme\ThemeRegistry;
use Naf\CMS\Visualization\VisualizationRegistry;

use function Naf\app;

final class Cms
{
    private ?ApiRegistry $apiRegistry                     = null;
    private ?Settings $settings                           = null;
    private ?ContentRegistry $contentRegistry             = null;
    private ?PageRegistry $pageRegistry                   = null;
    private ?VisualizationRegistry $visualizationRegistry = null;
    private ?LanguageRegistry $languageRegistry           = null;
    private ?BlockViewRegistry $blockViewRegistry         = null;
    private ?BlockTypeRegistry $blockTypeRegistry         = null;
    private ?ThemeRegistry $themeRegistry                 = null;

    public function scope(Scope|string|null $scope = null): ScopedCms
    {
        return new ScopedCms($this, $scope === null ? Scope::FRONTEND : Scope::normalize($scope));
    }

    public function settings(): Settings
    {
        return $this->settings ??= new Settings();
    }

    public function languages(): LanguageRegistry
    {
        return $this->languageRegistry ??= new LanguageRegistry();
    }

    public function pages(): PageRegistry
    {
        return $this->pageRegistry ??= new PageRegistry();
    }

    public function navigation(): NavigationApi
    {
        /** @var NavigationApi $api */
        $api = $this->content(SiteNavigation::class);

        return $api;
    }

    public function contentTypes(): ContentRegistry
    {
        if ($this->contentRegistry === null) {
            $this->contentRegistry = new ContentRegistry();
        }

        return $this->contentRegistry;
    }

    public function api(): ApiRegistry
    {
        return $this->apiRegistry ??= new ApiRegistry();
    }

    public function visualizations(): VisualizationRegistry
    {
        return $this->visualizationRegistry ??= new VisualizationRegistry();
    }

    public function blocks(): BlockViewRegistry
    {
        return $this->blockViewRegistry ??= new BlockViewRegistry();
    }

    public function blockTypes(): BlockTypeRegistry
    {
        return $this->blockTypeRegistry ??= new BlockTypeRegistry();
    }

    public function themes(): ThemeRegistry
    {
        if ($this->themeRegistry !== null) {
            return $this->themeRegistry;
        }

        $registry = new ThemeRegistry();
        /** @var ThemeSourceInterface $source */
        $source = app()->container()->get(ThemeSourceInterface::class);
        // Persisted themes intentionally replace the built-in default so users
        // can customize it without making the registry itself database-aware.
        $registry->addMany($source->themes(), true);

        return $this->themeRegistry = $registry;
    }

    public function articles(): ArticleApi
    {
        /** @var ArticleApi $api */
        $api = $this->content(Article::class);

        return $api;
    }

    public function forms(): FormApi
    {
        /** @var FormApi $api */
        $api = $this->content(Form::class);

        return $api;
    }

    public function media(): MediaApi
    {
        /** @var MediaApi $api */
        $api = $this->content(Media::class);

        return $api;
    }

    /**
     * @template T of \Naf\ORM\Core\EntityInterface
     * @param class-string<T> $class
     * @return EntityStorage<T>
     */
    public function storage(string $class): EntityStorage
    {
        return new EntityStorage($class);
    }

    /**
     * @param class-string $class
     */
    public function content(string $class): object
    {
        $type = $this->contentTypes()->findByModel($class);
        if ($type !== null) {
            return $type->api();
        }

        throw new InvalidArgumentException(sprintf(
            'No content type registered for "%s". Register one through cms()->contentTypes()->add(...).',
            $class,
        ));
    }
}
