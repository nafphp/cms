<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support;

use NixPHP\CMS\Api\ArticleApi;
use NixPHP\CMS\Api\ApiRegistry;
use NixPHP\CMS\Api\FormApi;
use NixPHP\CMS\Api\MediaApi;
use NixPHP\CMS\Api\NavigationApi;
use NixPHP\CMS\Content\BlockViewRegistry;
use NixPHP\CMS\Content\ContentRegistry;
use NixPHP\CMS\Content\Page\Block\BlockTypeRegistry;
use NixPHP\CMS\Contracts\ThemeSourceInterface;
use NixPHP\CMS\Enums\Scope;
use NixPHP\CMS\Localization\LanguageRegistry;
use NixPHP\CMS\Models\Article;
use NixPHP\CMS\Models\Form;
use NixPHP\CMS\Models\Media;
use NixPHP\CMS\Models\SiteNavigation;
use NixPHP\CMS\Page\PageRegistry;
use NixPHP\CMS\Storage\EntityStorage;
use NixPHP\CMS\Theme\ThemeRegistry;
use NixPHP\CMS\Visualization\VisualizationRegistry;
use NixPHP\CMS\Support\ScopedCms;
use NixPHP\CMS\Support\Settings;
use function NixPHP\app;

final class Cms
{
    private ?ApiRegistry $apiRegistry = null;
    private ?Settings $settings = null;
    private ?ContentRegistry $contentRegistry = null;
    private ?PageRegistry $pageRegistry = null;
    private ?VisualizationRegistry $visualizationRegistry = null;
    private ?LanguageRegistry $languageRegistry = null;
    private ?BlockViewRegistry $blockViewRegistry = null;
    private ?BlockTypeRegistry $blockTypeRegistry = null;
    private ?ThemeRegistry $themeRegistry = null;

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
     * @template T of \NixPHP\ORM\Core\EntityInterface
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

        throw new \InvalidArgumentException(sprintf(
            'No content type registered for "%s". Register one through cms()->contentTypes()->add(...).',
            $class
        ));
    }
}
