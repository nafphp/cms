<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Api\ArticleApi;
use NixCMS\Api\ApiRegistry;
use NixCMS\Content\ContentRegistry;
use NixCMS\Models\Article;
use NixCMS\Navigation\NavigationRegistry;
use NixCMS\Page\PageRegistry;
use NixCMS\Settings\SettingsRegistry;

final class Cms
{
    private ?ApiRegistry $apiRegistry = null;
    private ?Settings $settings = null;
    private ?ContentRegistry $contentRegistry = null;
    private ?PageRegistry $pageRegistry = null;
    private ?NavigationRegistry $navigationRegistry = null;
    private ?SettingsRegistry $settingsRegistry = null;

    public function settings(): Settings
    {
        return $this->settings ??= new Settings();
    }

    public function settingsRegistry(): SettingsRegistry
    {
        if ($this->settingsRegistry === null) {
            $this->settingsRegistry = new SettingsRegistry();
            $settingsFile = BASE_PATH . '/app/settings.php';
            if (is_file($settingsFile)) {
                $this->settingsRegistry->addMany(require $settingsFile);
            }
        }

        return $this->settingsRegistry;
    }

    public function pages(): PageRegistry
    {
        return $this->pageRegistry ??= new PageRegistry();
    }

    public function navigation(): NavigationRegistry
    {
        if ($this->navigationRegistry === null) {
            $this->navigationRegistry = new NavigationRegistry();
            $navigationFile = BASE_PATH . '/app/navigation.php';
            if (is_file($navigationFile)) {
                $this->navigationRegistry->addMany(require $navigationFile);
            }
        }

        return $this->navigationRegistry;
    }

    public function contentTypes(): ContentRegistry
    {
        if ($this->contentRegistry === null) {
            $this->contentRegistry = new ContentRegistry();
            $contentFile = BASE_PATH . '/app/content.php';
            if (is_file($contentFile)) {
                $this->contentRegistry->addMany(require $contentFile);
            }
        }

        return $this->contentRegistry;
    }

    public function api(): ApiRegistry
    {
        return $this->apiRegistry ??= new ApiRegistry();
    }

    public function articles(): ArticleApi
    {
        /** @var ArticleApi $api */
        $api = $this->content(Article::class);
        return $api;
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
