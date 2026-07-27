<?php

declare(strict_types=1);

namespace src\Support;

use use src\Api\ArticleApi;
use src\Api\ApiRegistry;
use src\Api\FormApi;
use src\Api\MediaApi;
use src\Api\NavigationApi;
use src\Content\BlockViewRegistry;
use src\Content\ContentRegistry;
use src\Content\Page\Block\BlockTypeRegistry;
use src\Enums\Scope;
use src\Localization\LanguageRegistry;
use src\Models\Article;
use src\Models\Form;
use src\Models\Media;
use src\Models\SiteNavigation;
use src\Page\PageRegistry;
use src\Repositories\ThemeRepository;
use src\Storage\EntityStorage;
use src\Theme\ThemeRegistry;
use src\Visualization\VisualizationRegistry;
use src\Support\ScopedCms;
use src\Support\Settings;

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
        if ($this->themeRegistry === null) {
            $this->themeRegistry = new ThemeRegistry();
            $this->themeRegistry->addMany((new ThemeRepository())->themes(), true);
        }

        return $this->themeRegistry;
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
